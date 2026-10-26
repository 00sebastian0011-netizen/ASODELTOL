"""Installer integration checks without root, VPS access, or service activation."""
from __future__ import annotations

from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
INSTALLER = ROOT / "scripts/diagnostics/vps/install-diagnostics.sh"


class TestDiagnosticsInstallationIntegration(unittest.TestCase):
    def test_validate_mode_checks_complete_mcp_and_worker_bundle(self):
        bash = shutil.which("bash")
        if not bash:
            self.skipTest("bash no disponible en el host local")
        installer_rel = INSTALLER.relative_to(ROOT).as_posix()
        result = subprocess.run([bash, installer_rel, "validate"], cwd=ROOT, text=True, capture_output=True, timeout=10, check=False)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("INSTALL_VALIDATION=PASS", result.stdout)

    def test_installer_contains_private_paths_backup_and_reversible_worker(self):
        text = INSTALLER.read_text(encoding="utf-8")
        for marker in (
            "validation_service.py", "production-test-catalog.json", "ASODELTOL_VALIDATION_STATE_DIR",
            "ASODELTOL_VALIDATION_RUN_AUTH_FILE", "asodeltol-validation-worker.service",
            "asodeltol-validation-worker.timer", "backup_manifest", "rollback", "activate-worker",
        ):
            self.assertIn(marker, text)
        self.assertIn("ASODELTOL_QA_ACCOUNT_FILE", text)
        self.assertIn("ASODELTOL_QA_CONFIG_FILE", text)
        worker_unit = (ROOT / "scripts/diagnostics/vps/asodeltol-validation-worker.service").read_text(encoding="utf-8")
        diagnostics_unit = (ROOT / "scripts/diagnostics/vps/asodeltol-diagnostics.service").read_text(encoding="utf-8")
        self.assertIn("MemoryMax=512M", worker_unit)
        self.assertIn("TasksMax=64", worker_unit)
        self.assertIn("ExecStart=/usr/bin/node", worker_unit)
        self.assertIn("MemoryMax=128M", diagnostics_unit)
        self.assertIn("TasksMax=16", diagnostics_unit)
        self.assertNotIn("playwright", diagnostics_unit.lower())


if __name__ == "__main__":
    unittest.main()
