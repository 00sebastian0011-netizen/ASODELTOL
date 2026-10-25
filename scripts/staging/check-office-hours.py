"""Exercise the actual Moodle form, CSRF denial and native calendar write locally."""
import importlib.util
import datetime
import json
import pathlib
import re
import subprocess
import urllib.error
import urllib.parse
from html.parser import HTMLParser

ROOT = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('report_checks', pathlib.Path(__file__).with_name('check-additional-reports.py'))
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)


class Form(HTMLParser):
    def __init__(self):
        super().__init__()
        self.fields = {}
        self.select = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') and attrs.get('name') != 'cancel':
            if attrs.get('type') not in ('checkbox', 'radio', 'button') or 'checked' in attrs:
                self.fields[attrs['name']] = attrs.get('value', '')
        if tag == 'select':
            self.select = attrs.get('name')
        if tag == 'option' and self.select:
            if self.select not in self.fields or 'selected' in attrs:
                self.fields[self.select] = attrs.get('value', '')

    def handle_endtag(self, tag):
        if tag == 'select':
            self.select = None


def context():
    raw = subprocess.run(['docker', 'exec', '--user', 'www-data', 'asodeltol-moodle53-webserver-1',
                          'php', '/opt/asodeltol/scripts/anexo14-report-context.php'],
                         text=True, capture_output=True, check=True)
    return json.loads(raw.stdout)


def main():
    before = context()
    client = helper.session('asodeltol_admin', ROOT / 'build/staging/moodle53/secrets/admin-password.txt')
    url = helper.BASE + '/local/asodeltolstaff/officehours.php?courseid=' + str(before['course_id'])
    page = client.open(url, timeout=60).read().decode()
    form = Form()
    form.feed(page)
    assert 'sesskey' in form.fields and form.fields.get('trainerid') and form.fields.get('groupid')
    form.fields['meetingurl'] = 'https://meeting.example.invalid/asodeltol-synthetic-qa'
    start = datetime.datetime(*(int(form.fields['start[' + key + ']']) for key in ('year', 'month', 'day', 'hour', 'minute')))
    end = start + datetime.timedelta(hours=2)
    for key in ('year', 'month', 'day', 'hour', 'minute'):
        form.fields['end[' + key + ']'] = str(getattr(end, key))
    without = {key: value for key, value in form.fields.items() if key != 'sesskey'}
    try:
        client.open(url, urllib.parse.urlencode(without).encode(), timeout=60).read()
    except urllib.error.HTTPError as error:
        denied = error.read().decode('utf-8', errors='replace')
        assert error.code in (400, 403, 404) and ('invalidsesskey' in denied
            or ('missingparam' in denied and 'sesskey' in denied)), 'Unexpected CSRF response'
    assert context()['office_hours_count'] == before['office_hours_count'], 'CSRF request changed calendar'
    response = client.open(url, urllib.parse.urlencode(form.fields).encode(), timeout=60)
    responsehtml = response.read().decode('utf-8', errors='replace')
    after = context()
    if after['office_hours_count'] != before['office_hours_count'] + 1:
        errors = re.findall(r'(?s)<[^>]*class="[^"]*invalid-feedback[^>]*>(.*?)</div>', responsehtml)
        print(json.dumps({'form_errors': [re.sub('<[^>]+>', '', value).strip() for value in errors],
                          'field_names': sorted(form.fields), 'before': before['office_hours_count'],
                          'dates': {k: v for k, v in form.fields.items() if k.startswith(('start[','end['))},
                          'after': after['office_hours_count']}, ensure_ascii=False))
    assert after['office_hours_count'] in (before['office_hours_count'], before['office_hours_count'] + 1)
    assert after['office_hours_count'] > 0, 'Valid form did not create or confirm a calendar slot'
    assert '/calendar/view.php' in response.url, 'Expected calendar redirect after save'
    assert after['course_hidden'] and after['category_hidden']
    out = ROOT / 'build/staging/anexo14-reports/office-hours-http.json'
    result = {'environment': helper.BASE, 'status': 'PASS',
              'checks': ['GET form contains group/trainer/date fields', 'CSRF denied without calendar write',
                         'valid authenticated POST creates or confirms one native calendar slot without duplication',
                         'redirect to native calendar', 'course/category remain hidden'],
              'synthetic_slot_retained': True, 'official_timetable': False,
              'office_hours_before': before['office_hours_count'], 'office_hours_after': after['office_hours_count']}
    out.write_text(json.dumps(result, indent=2), encoding='utf-8')
    print(json.dumps(result))


if __name__ == '__main__':
    main()
