"""Generate/validate the third-party AF8 content handoff, without publishing it."""
import argparse
import hashlib
import json
import pathlib
import sys
import zipfile
import openpyxl

ROOT = pathlib.Path(__file__).resolve().parents[2]


def catalog_items():
    catalog = json.loads((ROOT / 'catalog/af8/catalog.json').read_text(encoding='utf-8'))
    templates = {item['template_id']: item for item in catalog['component_templates']}
    items = [dict(item, section=0) for item in catalog['general_resources']]
    for unit in catalog['units']:
        for component in unit['components']:
            template = templates[component['template_id']]
            items.append(dict(component, section=unit['position'], title=template['title'],
                              moodle_type_options=template['moodle_type_options'],
                              selected_moodle_type=template['selected_moodle_type']))
    assert len(items) == 54
    return items


def generate(directory):
    directory.mkdir(parents=True, exist_ok=True)
    target = directory / 'manifest.json'
    if target.exists():
        raise ValueError('Existing manifest preserved; choose another output directory.')
    records = [{'code': item['code'], 'idnumber': item['stable_id'], 'section': item['section'],
                'title': item['title'], 'allowed_types': item['moodle_type_options'],
                'module_type': item['selected_moodle_type'], 'file': None, 'sha256': None,
                'external_url': None, 'author': None, 'license': None, 'review_reference': None,
                'subtitles_file': None, 'review_state': 'awaiting_third_party'} for item in catalog_items()]
    payload = {'course_idnumber': 'ASODELTOL-AF8', 'schema_version': 1,
               'official_acceptance': False, 'records': records}
    target.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding='utf-8')
    book = openpyxl.Workbook()
    sheet = book.active
    sheet.title = 'Carga tercero AF8'
    keys = list(records[0])
    sheet.append(keys)
    for record in records:
        sheet.append([', '.join(record[key]) if isinstance(record[key], list) else record[key] for key in keys])
    sheet.freeze_panes = 'A2'
    sheet.auto_filter.ref = sheet.dimensions
    book.save(directory / 'AF8-insumos-tercero.xlsx')
    print(json.dumps({'status': 'TEMPLATE_CREATED', 'items': len(records), 'academic_content_created': False}))


def resolve_file(root, relative):
    if not isinstance(relative, str) or not relative or pathlib.PurePath(relative).is_absolute():
        raise ValueError('Relative asset path required')
    if '\\' in relative or ':' in relative or '..' in pathlib.PurePosixPath(relative).parts:
        raise ValueError('Unsafe asset path')
    path = (root / relative).resolve()
    if not path.is_relative_to(root.resolve()) or not path.is_file():
        raise ValueError('Asset missing or outside package')
    return path


def validate(manifest):
    payload = json.loads(manifest.read_text(encoding='utf-8'))
    assert payload['course_idnumber'] == 'ASODELTOL-AF8'
    actual = payload['records']
    expected = {r['code']: r for r in catalog_items()}
    if len(actual) != 54 or len({r['code'] for r in actual}) != 54 or {r['code'] for r in actual} != set(expected):
        raise ValueError('Exactly the 54 catalog identifiers are required; no extra or duplicate codes.')
    checks = []
    for record in actual:
        pending = []
        invalid = []
        item = expected[record['code']]
        for key, correct in [('idnumber', item['stable_id']), ('section', item['section'])]:
            if record.get(key) != correct:
                invalid.append('Catalog mismatch: ' + key)
        module = record.get('module_type')
        if not module:
            pending.append('module_type')
        elif module not in item['moodle_type_options']:
            invalid.append('Unsupported module type for this catalog item')
        for key in ('author', 'license', 'review_reference'):
            if not record.get(key):
                pending.append(key)
        if record.get('review_state') != 'reviewed':
            pending.append('third_party_review')
        if module == 'url':
            from urllib.parse import urlsplit
            url = urlsplit(record.get('external_url') or '')
            if not url.netloc or url.scheme != 'https' or url.username or url.password:
                pending.append('approved_https_url')
        elif not record.get('file'):
            pending.append('content_file_or_native_activity')
        else:
            try:
                path = resolve_file(manifest.parent, record['file'])
                data = path.read_bytes()
                if hashlib.sha256(data).hexdigest() != record.get('sha256'):
                    invalid.append('SHA256 mismatch')
                suffix = path.suffix.lower()
                if suffix in ('.xlsx', '.docx', '.pptx'):
                    with zipfile.ZipFile(path) as bundle:
                        if '[Content_Types].xml' not in bundle.namelist():
                            invalid.append('Invalid Office package')
                elif suffix == '.pdf' and not data.startswith(b'%PDF-'):
                    invalid.append('Invalid PDF signature')
                elif suffix in ('.html', '.htm') and (b'<script' in data.lower() or b'javascript:' in data.lower()):
                    invalid.append('Active scripting not permitted in content package')
                if suffix in ('.mp4', '.webm', '.mov'):
                    pending.append('measured_video_codec_resolution_and_accessibility')
                if record.get('subtitles_file'):
                    resolve_file(manifest.parent, record['subtitles_file'])
            except (ValueError, OSError, zipfile.BadZipFile) as error:
                invalid.append(str(error))
        checks.append({'code': record['code'], 'status': 'FAIL' if invalid else 'BLOCKED' if pending else 'READY_FOR_REVIEW',
                       'pending': pending, 'errors': invalid})
    result = {'scope': 'Third-party handoff validation only; no Moodle or VPS writes',
              'official_acceptance': False, 'checks': checks,
              'counts': {status: sum(c['status'] == status for c in checks) for status in ('READY_FOR_REVIEW', 'BLOCKED', 'FAIL')}}
    (manifest.parent / 'validation.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'counts': result['counts'], 'official_acceptance': False}))
    return 1 if result['counts']['FAIL'] else 2 if result['counts']['BLOCKED'] else 0


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['generate', 'validate'])
    parser.add_argument('path', type=pathlib.Path)
    options = parser.parse_args()
    if options.action == 'generate':
        generate(options.path)
    else:
        sys.exit(validate(options.path))
