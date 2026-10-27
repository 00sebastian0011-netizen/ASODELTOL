"""Unit tests for the closed operational MCP surface; no VPS or Moodle needed."""
from __future__ import annotations
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest

ROOT = Path(tempfile.mkdtemp())
(ROOT / "token").write_text("x" * 48)
for name in ("approvals", "state", "site", "repo", "deploystate", "ops"):
    (ROOT / name).mkdir(exist_ok=True)
os.environ.update({
    "ASODELTOL_OPS_TOKEN_FILE": str(ROOT / "token"), "ASODELTOL_OPS_APPROVAL_DIR": str(ROOT / "approvals"),
    "ASODELTOL_OPS_STATE_DIR": str(ROOT / "state"), "ASODELTOL_OPS_ROOT": str(ROOT / "ops"),
    "ASODELTOL_SITE_ROOT": str(ROOT / "site"), "ASODELTOL_REPO_DIR": str(ROOT / "repo"),
    "ASODELTOL_DEPLOY_STATE_DIR": str(ROOT / "deploystate"),
})
source = Path(__file__).resolve().parents[2] / "scripts" / "operations" / "vps" / "operations_server.py"
spec = importlib.util.spec_from_file_location("operations_server", source)
module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)

class OperationsMcpTest(unittest.TestCase):
    def test_tools_are_closed_and_require_authorization(self):
        _, result = module.rpc({"jsonrpc": "2.0", "id": 1, "method": "tools/list"})
        names = {item["name"] for item in result["result"]["tools"]}
        self.assertEqual(names, {"operations_backup", "staff_deploy_0600", "moodle_upgrade", "policy_publish", "policy_verify", "operations_rollback"})
        self.assertNotIn("shell", names)
        _, rejected = module.rpc({"jsonrpc": "2.0", "id": 2, "method": "tools/call", "params": {"name": "policy_publish", "arguments": {}}})
        self.assertEqual(rejected["error"]["code"], -32602)

    def test_authorization_rejects_missing_and_mismatched_scope(self):
        with self.assertRaises((FileNotFoundError, ValueError)):
            module.approval("backup", "a" * 16, {"label": "x"})
        path = ROOT / "approvals" / ("b" * 16 + ".json")
        path.write_text(json.dumps({"operation": "backup", "parameters": {"label": "x"}, "expires_at": 1}))
        with self.assertRaises(ValueError):
            module.approval("backup", "b" * 16, {"label": "x"})

    def test_backup_and_rollback_cover_database_code_and_moodledata(self):
        vps = source.parent
        backup = (vps / "backup.php").read_text(encoding="utf-8")
        rollback = (vps / "rollback.php").read_text(encoding="utf-8")
        for text in (backup, rollback):
            self.assertIn("ASODELTOL_MOODLEDATA", text)
            self.assertIn("moodledata", text)
            self.assertIn("sha256", text)
        self.assertIn("--single-transaction", backup)
        self.assertIn("maintenance.php", backup)
        self.assertIn("maintenance.php", rollback)

if __name__ == "__main__": unittest.main()
