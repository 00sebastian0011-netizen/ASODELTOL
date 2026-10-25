"""Actual local course/group/download QA; existing accounts and preserved fixtures."""
import datetime
import hashlib
import importlib.util
import json
import pathlib
import re
import subprocess
import urllib.error
import urllib.parse

ROOT = pathlib.Path(__file__).resolve().parents[2]
folder = pathlib.Path(__file__).parent
def module(name, filename):
    spec = importlib.util.spec_from_file_location(name, folder / filename)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result
helper = module('report_helpers', 'check-additional-reports.py')
forms = module('office_helpers', 'check-office-hours.py')
WEB = 'asodeltol-moodle53-webserver-1'


def phase(name):
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                             '/opt/asodeltol/scripts/anexo14-functional-context.php', '--phase=' + name],
                            text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


def page(client, path):
    try:
        return client.open(helper.BASE + path, timeout=120).read().decode('utf-8', errors='replace')
    except urllib.error.HTTPError as error:
        assert error.code in (400, 403, 404), 'Unexpected HTTP status'
        return error.read().decode('utf-8', errors='replace')


def snapshot():
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                             '/opt/asodeltol/scripts/moodle53-functional-pilot.php', '--phase=snapshot'],
                            text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


def main():
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    out = ROOT / 'build/staging/anexo14-functional' / stamp
    out.mkdir(parents=True, exist_ok=True)
    checks = []
    result = {'environment': helper.BASE, 'checks': checks, 'official_acceptance': False}
    try:
        info = phase('provision-files')
        assert info['course_hidden'] and info['category_hidden'] and len(info['resources']) == 5
        admin = helper.session('asodeltol_admin', ROOT / 'build/staging/moodle53/secrets/admin-password.txt')
        if not info['page_cmid']:
            url = helper.BASE + '/local/asodeltolstaff/publish.php?courseid=' + str(info['course_id'])
            form = forms.Form()
            form.feed(admin.open(url, timeout=60).read().decode())
            assert 'sesskey' in form.fields
            form.fields.update({'groupid': str(info['group_id']), 'section': '1', 'kind': 'page',
                                'title': 'ASODELTOL-A14-SYNTHETIC-GROUP-PAGE',
                                'reference': 'SYNTHETIC QA ONLY - NOT INSTITUTIONAL APPROVAL',
                                'body[text]': '<p>ASODELTOL-PUBLISHER-SYNTHETIC-ONLY-G01</p>',
                                'body[format]': '1', 'body[itemid]': '0'})
            without = {key: value for key, value in form.fields.items() if key != 'sesskey'}
            try:
                admin.open(url, urllib.parse.urlencode(without).encode(), timeout=60).read()
            except urllib.error.HTTPError as error:
                text = error.read().decode(errors='replace')
                assert 'invalidsesskey' in text or ('missingparam' in text and 'sesskey' in text)
            assert phase('inspect')['module_count'] == info['module_count'], 'CSRF wrote a module'
            created = admin.open(url, urllib.parse.urlencode(form.fields).encode(), timeout=60)
            created.read()
            info = phase('inspect')
            assert info['page_cmid'], 'Actual publish form did not create the group page'
            checks.append({'id': 'PUBLISH-HTTP-CSRF', 'status': 'PASS'})
        else:
            checks.append({'id': 'PUBLISH-HTTP-FIXTURE', 'status': 'PASS', 'detail': 'Existing preserved synthetic page reused'})
        phase('unlock')
        state = snapshot()
        # Populate only the pre-existing, documented M07 QA activity when both
        # synthetic records are absent. Never overwrite another grading run.
        if all(row['grade'] is None and row['status'] is None for row in state['submissions'].values()):
            seeded = subprocess.run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                                     '/opt/asodeltol/scripts/moodle53-functional-pilot.php', '--phase=exercise'],
                                    text=True, capture_output=True, check=True)
            result['synthetic_m07_fixtures_created'] = True
            state = snapshot()
        else:
            result['synthetic_m07_fixtures_created'] = False
        secret = ROOT / 'build/staging/moodle53/secrets/student-password.txt'
        first = helper.session('af8.synthetic.g01', secret)
        second = helper.session('af8.synthetic.g02', secret)
        trainer = helper.session('af8.synthetic.trainer.g01', secret)
        auditor = helper.session('af8.synthetic.auditor', secret)
        for item in info['resources']:
            response = first.open(item['url'], timeout=60)
            data = response.read()
            assert hashlib.sha256(data).hexdigest() == item['sha256'], 'Authorized file bytes differ'
            try:
                denied = second.open(item['url'], timeout=60).read()
            except urllib.error.HTTPError as error:
                assert error.code in (400, 403, 404)
                denied = error.read()
            assert hashlib.sha256(denied).hexdigest() != item['sha256'], 'Other group obtained the file'
            checks.append({'id': 'FILE-' + pathlib.Path(item['filename']).suffix[1:].upper(), 'status': 'PASS',
                           'sha256': item['sha256'], 'cross_group_denied': True})
        own = page(first, '/mod/page/view.php?id=' + str(info['page_cmid']))
        other = page(second, '/mod/page/view.php?id=' + str(info['page_cmid']))
        assert 'ASODELTOL-PUBLISHER-SYNTHETIC-ONLY-G01' in own and 'ASODELTOL-PUBLISHER-SYNTHETIC-ONLY-G01' not in other
        checks.append({'id': 'GROUP-PAGE', 'status': 'PASS'})
        courseid = info['course_id']
        peerid = info['actors']['af8.synthetic.g02']['id']
        ownid = info['actors']['af8.synthetic.g01']['id']
        cmid = state['activities']['assignment']['cm_id']
        peer_submission = page(trainer, f'/mod/assign/view.php?id={cmid}&action=grader&userid={peerid}')
        teacher_grades = page(trainer, f'/grade/report/grader/index.php?id={courseid}')
        assert 'M07-G02-SUBMISSION-MARKER' not in peer_submission and 'AF8-G02' not in teacher_grades
        peer_group = next(group['group_id'] for group in state['course']['groups'] if group['code'] == 'AF8-G02')
        forged_group = page(trainer, f'/grade/report/grader/index.php?id={courseid}&group={peer_group}')
        assert '97.00' not in forged_group, 'Forged group selection exposed the G02 fixture grade'
        assert phase('verify-access')['native_teacher_other_group_denied']
        checks.append({'id': 'TRAINER-GROUP-ISOLATION', 'status': 'PASS'})
        own_grades = page(first, f'/grade/report/user/index.php?id={courseid}')
        assert 'page-grade-report-user-index' in own_grades, 'Own grade report did not render'
        assert f'{state["submissions"]["G01"]["grade"]:.2f}' in own_grades, 'Expected synthetic grade was not rendered'
        peer_grades = page(first, f'/grade/report/user/index.php?id={courseid}&userid={peerid}')
        assert '97.00' not in peer_grades, 'Other learner grade leaked'
        checks.append({'id': 'LEARNER-GRADES-OWN-ONLY', 'status': 'PASS'})
        for target in (ownid, peerid):
            viewed = page(auditor, f'/grade/report/user/index.php?id={courseid}&userid={target}')
            assert 'page-grade-report-user-index' in viewed
            code = 'G01' if target == ownid else 'G02'
            assert f'{state["submissions"][code]["grade"]:.2f}' in viewed, 'Auditor did not receive the recorded fixture grade'
        denied = page(auditor, f'/grade/edit/tree/grade.php?courseid={courseid}&itemid={info["grade_item_id"]}&userid={ownid}')
        assert 'name="finalgrade"' not in denied
        checks.append({'id': 'AUDITOR-GRADES-READ-ONLY', 'status': 'PASS'})
        for client, target in [(first, ownid), (trainer, ownid), (auditor, peerid)]:
            progress = page(client, f'/local/asodeltolstaff/progress.php?courseid={courseid}&userid={target}')
            assert 'asodeltol-overall-progress' in progress and 'asodeltol-unit-progress' in progress
            assert re.search(r'id="asodeltol-overall-progress"[^>]*>[^<]*[0-9]+[.,][0-9]+%', progress), 'No numeric progress rendered'
            table = re.search(r'<table[^>]*id="asodeltol-unit-progress"[^>]*>.*?</table>', progress, re.S)
            assert table and len(re.findall(r'<tr\b', table[0])) >= 6, 'Five unit rows were not rendered'
        denied = page(trainer, f'/local/asodeltolstaff/progress.php?courseid={courseid}&userid={peerid}')
        assert 'asodeltol-unit-progress' not in denied
        checks.append({'id': 'PROGRESS-ROLE-SCOPE', 'status': 'PASS'})
        discussion = state['activities']['forum']['discussion_id']
        if discussion:
            denied = page(first, f'/mod/forum/discuss.php?d={discussion}')
            assert 'M07-G02-DIRECT-ACCESS-DENIED-MARKER' not in denied
            own_forum = page(second, f'/mod/forum/discuss.php?d={discussion}')
            assert 'M07-G02-DIRECT-ACCESS-DENIED-MARKER' in own_forum
            assert re.search(r'/mod/forum/post\.php\?reply=', own_forum), 'No reply action available for the group forum'
            checks.append({'id': 'GROUP-FORUM', 'status': 'PASS'})
        after = snapshot()
        assert state['submissions'] == after['submissions'], 'Read-only checks changed grades or submissions'
        checks.append({'id': 'GRADE-SUBMISSION-INTEGRITY', 'status': 'PASS'})
        result['status'] = 'PASS'
    except Exception as error:
        result['status'] = 'FAIL'
        result['error'] = type(error).__name__ + ': ' + str(error)
    finally:
        phase('lock')
        final = phase('inspect')
        assert final['course_hidden'] and final['category_hidden']
        result['final_hidden'] = True
        result['synthetic_resources_retained'] = len(final['resources'])
        (out / 'summary.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
        print(json.dumps(result))
    return 0 if result['status'] == 'PASS' else 1


if __name__ == '__main__':
    raise SystemExit(main())
