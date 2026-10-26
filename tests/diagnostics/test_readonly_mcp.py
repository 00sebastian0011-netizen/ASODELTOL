"""No dependency on the real VPS, secrets, Moodle accounts or database."""
from __future__ import annotations

from http.server import ThreadingHTTPServer
from pathlib import Path
import json
import os
import tempfile
import threading
import unittest
import urllib.error
import urllib.request
import sys

SRC = Path(__file__).resolve().parents[2] / "scripts" / "diagnostics" / "vps"
sys.path.insert(0, str(SRC))
TEST_DIR = tempfile.TemporaryDirectory()
ROOT = Path(TEST_DIR.name)
TOKEN = "u" * 64
(ROOT / "token").write_text(TOKEN)
for field, value in {
    "ASODELTOL_DIAG_TOKEN_FILE": ROOT / "token",
    "ASODELTOL_DIAG_SITE_ROOT": ROOT / "site",
    "ASODELTOL_DIAG_MOODLEDATA": ROOT / "moodledata",
    "ASODELTOL_DIAG_STATE_ROOT": ROOT / "state",
    "ASODELTOL_DIAG_VHOST": ROOT / "vhost",
}.items():
    os.environ[field] = str(value)
import probes
import mcp_server


class TestSanitizedProbes(unittest.TestCase):
    def setUp(self):
        for name in ("site", "moodledata", "state"):
            (ROOT / name).mkdir(exist_ok=True)

    def test_versions_dont_read_config_php(self):
        folder = ROOT / "site" / "public"
        folder.mkdir(parents=True, exist_ok=True)
        (folder / "version.php").write_text("<?php $release = '5.3.0'; $version = 2026100200;")
        (folder / "config.php").write_text("password = 'PRIVATE_SECRET';")
        result = probes.versions()
        self.assertEqual(result["components"]["moodle"]["release"], "5.3.0")
        self.assertEqual(result["components"]["moodle"]["version"], "2026100200")
        self.assertNotIn("PRIVATE_SECRET", json.dumps(result))

    def test_static_upload_config_never_claims_real_upload(self):
        (ROOT / "vhost").write_text(
            'client_max_body_size 1G;\nPHP_VALUE "upload_max_filesize=1G\\npost_max_size=1100M";\npassword=PRIVATE')
        result = probes.upload_limits()
        self.assertEqual(result["limits"]["nginx_client_max_body_size"], ["1G"])
        self.assertEqual(result["limits"]["php_post_max_size"], ["1100M"])
        self.assertIs(result["moodle_authenticated_upload_verified"], False)
        self.assertNotIn("PRIVATE", json.dumps(result))

    def test_deployment_state_never_returns_sensitive_paths(self):
        folder = ROOT / "state" / "components"
        folder.mkdir(parents=True, exist_ok=True)
        (folder / "theme_rsmax.json").write_text(json.dumps({
            "base_sha": "a" * 40,
            "protected": ["config.php", "private_student_data.xlsx"],
            "expected": {"config.php": "sensitive"},
            "last_status": "deployed",
        }))
        result = probes.deploy_status()
        self.assertEqual(result["components"]["theme_rsmax"]["protected_files_count"], 2)
        self.assertEqual(result["components"]["theme_rsmax"]["base_sha"], "a" * 40)
        self.assertNotIn("private_student_data", json.dumps(result))
        self.assertNotIn("config.php", json.dumps(result))

    def test_four_allowlisted_tools(self):
        self.assertTrue({
            "vps_system_health", "moodle_versions", "upload_limits", "deployment_status",
        } <= set(probes.TOOLS))


class TestRpc(unittest.TestCase):
    def test_initialize_and_tool_metadata(self):
        status, data = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 1, "method": "initialize"})
        self.assertEqual(status, 200)
        self.assertEqual(data["result"]["protocolVersion"], "2025-06-18")
        status, data = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 2, "method": "tools/list"})
        self.assertEqual(len(data["result"]["tools"]), 9)
        names = {tool["name"] for tool in data["result"]["tools"]}
        self.assertTrue({"vps_system_health", "moodle_versions", "upload_limits", "deployment_status"} <= names)
        self.assertTrue({"validation_catalog", "run_validation", "validation_status", "validation_results", "validation_evidence"} <= names)
        for tool in [item for item in data["result"]["tools"] if item["name"] in probes.TOOLS]:
            self.assertTrue(tool["annotations"]["readOnlyHint"])
            self.assertFalse(tool["annotations"]["destructiveHint"])
            self.assertEqual(tool["inputSchema"]["properties"], {})
        for tool in [item for item in data["result"]["tools"] if item["name"].startswith("validation_") or item["name"] == "run_validation"]:
            self.assertFalse(tool["annotations"]["destructiveHint"])
            self.assertFalse(tool["inputSchema"].get("additionalProperties", True))

    def test_rejects_arbitrary_commands_paths_and_methods(self):
        for name, args in [
            ("ssh_exec", {}),
            ("upload_limits", {"path": "/etc/shadow"}),
            ("vps_system_health", {"command": "rm -rf /"}),
        ]:
            _, resp = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                                              "params": {"name": name, "arguments": args}})
            self.assertEqual(resp["error"]["code"], -32602)
        _, resp = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 4, "method": "resources/read"})
        self.assertEqual(resp["error"]["code"], -32601)


class TestHttp(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.httpd = ThreadingHTTPServer(("127.0.0.1", 0), mcp_server.Handler)
        cls.port = cls.httpd.server_port
        cls.worker = threading.Thread(target=cls.httpd.serve_forever, daemon=True)
        cls.worker.start()

    @classmethod
    def tearDownClass(cls):
        cls.httpd.shutdown()
        cls.httpd.server_close()

    def request(self, path, method="GET", *, token=TOKEN, body=None, origin=None):
        headers = {}
        if token is not None:
            headers["Authorization"] = "Bearer " + token
        if origin is not None:
            headers["Origin"] = origin
        data = None if body is None else json.dumps(body).encode()
        req = urllib.request.Request(f"http://127.0.0.1:{self.port}{path}",
                                     data=data, method=method, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=3) as response:
                return response.status, json.loads(response.read())
        except urllib.error.HTTPError as exc:
            return exc.code, json.loads(exc.read())

    def test_authentication_and_origin_required(self):
        self.assertEqual(self.request("/__asodeltol_diag/v1/system", token=None)[0], 401)
        self.assertEqual(self.request("/__asodeltol_diag/v1/system", token="wrong")[0], 401)
        self.assertEqual(self.request("/__asodeltol_diag/v1/system", origin="https://evil.invalid")[0], 403)
        self.assertEqual(self.request("/mcp", method="POST", token=None,
                                      body={"jsonrpc": "2.0", "id": 1, "method": "tools/list"})[0], 401)

    def test_rest_routes_whitelisted(self):
        for name in ("system", "versions", "uploads", "deploy"):
            code, payload = self.request("/__asodeltol_diag/v1/" + name)
            self.assertEqual(code, 200)
            self.assertIn("checked_at", payload)
        for name in ("config.php", "logs", "database", "../system", "execute"):
            self.assertEqual(self.request("/__asodeltol_diag/v1/" + name)[0], 404)

    def test_mcp_authorized_call(self):
        code, data = self.request("/mcp", method="POST",
                                  body={"jsonrpc": "2.0", "id": 9, "method": "tools/call",
                                        "params": {"name": "moodle_versions", "arguments": {}}})
        self.assertEqual(code, 200)
        self.assertFalse(data["result"]["isError"])
        self.assertIn("components", data["result"]["structuredContent"])


if __name__ == "__main__":
    unittest.main()
