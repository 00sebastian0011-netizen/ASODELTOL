import csv
import json
import pathlib
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts' / 'anexo14' / 'audit-vps.php'
MATRIX = ROOT / 'docs' / 'anexo14' / 'MATRIZ_ASODELTOL_82.md'


class AuditCliTest(unittest.TestCase):
    def test_dry_run_emits_82_sanitized_requirements_and_csv(self):
        with tempfile.TemporaryDirectory() as directory:
            completed = subprocess.run(['php', str(SCRIPT), '--dry-run', f'--matrix={MATRIX}', f'--output-dir={directory}'], capture_output=True, text=True, check=True)
            summary = json.loads(completed.stdout)
            self.assertEqual(summary['summary']['BLOCKED'], 82)
            payload = json.loads(pathlib.Path(summary['json']).read_text(encoding='utf-8'))
            self.assertEqual(len(payload['requirements']), 82)
            self.assertTrue(payload['read_only'])
            self.assertNotIn('config.php', json.dumps(payload))
            with pathlib.Path(summary['csv']).open(newline='', encoding='utf-8') as handle:
                self.assertEqual(len(list(csv.reader(handle))), 83)

    def test_rejects_non_82_matrix(self):
        with tempfile.TemporaryDirectory() as directory:
            bad = pathlib.Path(directory) / 'bad.md'
            source = MATRIX.read_text(encoding='utf-8')
            bad.write_text('\n'.join(line for line in source.splitlines() if not line.startswith('| REQ-01-01 |')) + '\n', encoding='utf-8')
            completed = subprocess.run(['php', str(SCRIPT), '--dry-run', f'--matrix={bad}', f'--output-dir={directory}'], capture_output=True, text=True)
            self.assertNotEqual(completed.returncode, 0)


if __name__ == '__main__':
    unittest.main()
