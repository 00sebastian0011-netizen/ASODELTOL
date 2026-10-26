"""Redact authentication query values from locally generated browser evidence, preserving results."""
import hashlib
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19'


def main():
    secrets = [p.read_text().strip() for p in (OUT / 'private').glob('*password*.txt')]
    historypath = OUT / 'sanitization.json'
    history = json.loads(historypath.read_text()) if historypath.exists() else []
    for pattern in ['browser-*/results.json', 'workflows-*/results.json', 'render50-*.json', 'links.json', 'youtube.json']:
        for p in OUT.glob(pattern):
            before = p.read_text(encoding='utf-8-sig')
            after = before
            for secret in secrets:
                if secret:
                    after = after.replace(secret, '[REDACTED]')
            after = re.sub(r'((?:sesskey|logintoken|token|password)=)[^&\s"<>]+', r'\1[REDACTED]', after, flags=re.I)
            if after != before:
                json.loads(after)
                history.append({'path': p.relative_to(OUT).as_posix(), 'before_sha256': hashlib.sha256(before.encode()).hexdigest(),
                                'after_sha256': hashlib.sha256(after.encode()).hexdigest(), 'reason': 'auth query redaction only'})
                p.write_text(after, encoding='utf-8')
    historypath.write_text(json.dumps(history, indent=2), encoding='utf-8')
    print('Evidence privacy redaction completed; test outcomes retained.')


if __name__ == '__main__':
    main()
