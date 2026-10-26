"""Validation MCP tests: bounded inputs, no secrets, no arbitrary execution."""
from __future__ import annotations

import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SRC = Path(__file__).resolve().parents[2] / "scripts" / "diagnostics" / "vps"
ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(SRC))
from validation_service import ValidationInputError, ValidationService  # noqa: E402
import mcp_server  # noqa: E402


class TestValidationService(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.state = Path(self.temp.name) / "state"
        self.service = ValidationService(ROOT / "docs/anexo14/qa/production-test-catalog.json", self.state)
        self.authorization = Path(self.temp.name) / "run-authorization.json"
        self.authorization.write_text(json.dumps({"reference": "ASO-VAL-TEST-20261026", "purpose": "production_validation_read_only", "expires_at": "2099-01-01T00:00:00Z", "allowed_case_ids": ["*"]}))
        self.service.run_authorization_file = self.authorization
        mcp_server.VALIDATION_SERVICE = self.service

    def tearDown(self):
        self.temp.cleanup()

    def test_catalog_is_compact_and_filters_requirements(self):
        result = self.service.catalog({"requirement_ids": ["REQ-06-02"], "limit": 5})
        self.assertLessEqual(result["count"], 5)
        self.assertTrue(all("case_id" in case for case in result["cases"]))
        self.assertTrue(any("REQ-06-02" in case["requirements"] for case in result["cases"]))

    def test_run_rejects_unknown_or_arbitrary_inputs(self):
        with self.assertRaises(ValidationInputError):
            self.service.run({"case_ids": ["TC-UNKNOWN"]})
        with self.assertRaises(ValidationInputError):
            self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "shell": "id"})
        with self.assertRaises(ValidationInputError):
            self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"] * 6})

    def test_run_requires_separate_authorization_and_rate_limit(self):
        denied = ValidationService(ROOT / "docs/anexo14/qa/production-test-catalog.json", self.state / "denied")
        result = denied.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        self.assertEqual(result["status"], "BLOCKED")
        wrong_ref = self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "authorization_ref": "ASO-VAL-WRONG-20261026"})
        self.assertEqual(wrong_ref["status"], "BLOCKED")
        for case_id in ["TC-VIDEO-PLAYBACK", "TC-NAVIGATION-MODULES", "TC-QUICKLINKS-SIX"]:
            self.service.run({"case_ids": [case_id], "authorization_ref": "ASO-VAL-TEST-20261026"})
        limited = self.service.run({"case_ids": ["TC-ACTIVITIES-BLOCKS"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        self.assertEqual(limited["reason"], "validation_rate_limit")

    def test_run_deduplicates_and_blocks_mutation_case(self):
        first = self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        second = self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        self.assertEqual(first["validation_id"], second["validation_id"])
        blocked = self.service.run({"case_ids": ["TC-FILE-UPLOAD-READONLY"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        self.assertEqual(blocked["status"], "BLOCKED")
        results = self.service.results({"validation_id": blocked["validation_id"]})
        self.assertEqual(results["results"][0]["status"], "BLOCKED")
        policy_write = self.service.run({"case_ids": ["TC-POLICY-NEW-ACCEPT"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        self.assertEqual(policy_write["status"], "BLOCKED")

    def test_rpc_exposes_validation_tools_without_shell(self):
        status, response = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 7, "method": "tools/call", "params": {"name": "validation_catalog", "arguments": {"limit": 1}}})
        self.assertEqual(status, 200)
        self.assertFalse(response["result"]["isError"] if "result" in response else True)
        _, rejected = mcp_server.handle_rpc({"jsonrpc": "2.0", "id": 8, "method": "tools/call", "params": {"name": "run_validation", "arguments": {"case_ids": ["TC-VIDEO-PLAYBACK"], "command": "id"}}})
        self.assertEqual(rejected["error"]["code"], -32602)

    def test_executor_failure_is_sanitized_and_does_not_need_credentials(self):
        queued = self.service.run({"case_ids": ["TC-VIDEO-PLAYBACK"], "authorization_ref": "ASO-VAL-TEST-20261026"})
        env = {**os.environ, "ASODELTOL_VALIDATION_STATE_DIR": str(self.state), "ASODELTOL_VALIDATION_CATALOG_FILE": str(ROOT / "docs/anexo14/qa/production-test-catalog.json")}
        completed = subprocess.run(["node", str(SRC / "validation_executor.mjs"), "--once"], cwd=ROOT, env=env, capture_output=True, text=True, timeout=10, check=True)
        self.assertNotIn("password", completed.stdout.lower())
        state = self.service.status({"validation_id": queued["validation_id"]})
        self.assertEqual(state["status"], "BLOCKED")
        details = self.service.results({"validation_id": queued["validation_id"]})
        self.assertEqual(details["results"][0]["status"], "BLOCKED")
        self.assertNotIn("ASODELTOL", json.dumps(details["results"][0]))


if __name__ == "__main__":
    unittest.main()
