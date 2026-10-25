"""Reconcile the latest synthetic CSV and XLSX downloads, without printing names."""
import csv
import datetime
import json
import pathlib
import openpyxl

root = pathlib.Path(__file__).resolve().parents[2]
directory = root / 'build/staging/anexo14-qa'
csvpath = max(directory.glob('export_*.csv'), key=lambda p: p.stat().st_mtime)
xlsxpath = csvpath.with_suffix('.xlsx')
with csvpath.open(encoding='utf-8-sig', newline='') as stream:
    text = stream.read()
    dialect = csv.Sniffer().sniff(text[:4096], delimiters=',;\t')
    csvrows = list(csv.reader(text.splitlines(), dialect))
book = openpyxl.load_workbook(xlsxpath, data_only=True)
excelrows = list(book.worksheets[0].values)
assert len(csvrows) == len(excelrows) and len(csvrows) > 1, 'Row counts differ or no data'
normalize = lambda value: '' if value is None else str(value)
for crow, xrow in zip(csvrows, excelrows):
    assert crow == [normalize(v) for v in xrow], 'CSV/XLSX content mismatch'
assert len(csvrows[0]) == 18, 'Expected 12 original columns, 4 UTC timestamps and 2 action identifiers'
for row in csvrows[1:]:
    for value in row[2:6]:
        if value:
            datetime.datetime.strptime(value, '%Y-%m-%dT%H:%M:%SZ')
assert {'M11-AF8-G01-01', 'M11-AF8-G02-01'} <= {r[0] for r in csvrows[1:]}
assert all(row[-2] == 'ASODELTOL-AF8' and row[-1] for row in csvrows[1:]), 'Training action missing'
result = {'status': 'PASS', 'csv': csvpath.name, 'xlsx': xlsxpath.name,
          'rows': len(csvrows)-1, 'columns': 18,
          'checks': ['CSV/XLSX identical cells', 'both synthetic groups present',
                     'scheduled/reported UTC timestamps parse', 'empty timestamps remain empty',
                     'training action code and name present'],
          'contractual_acceptance': False}
(directory / 'export-content.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
