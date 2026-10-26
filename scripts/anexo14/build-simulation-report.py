import json
from pathlib import Path
root=Path(__file__).resolve().parents[2]
t=json.loads((root/'docs/anexo14/TRAZABILIDAD_82_2026-10-25.json').read_text(encoding='utf-8'))
lines=['# Resultado de simulación integral de 82 requisitos','',f"Versión candidata: `{t.get('base_commit')}`. Entorno: staging local aislado. La evidencia técnica no implica aceptación institucional.",'','| ID | Prueba realizada | Resultado | Tipo | Evidencia | Estado técnico | Estado institucional | Falta para aceptación |','|---|---|---|---|---|---|---|---|']
for r in t['rows']:
    ev=r.get('evidence',[]); ev='; '.join(ev) if isinstance(ev,list) else str(ev)
    state=r['state']; result='PASS_REAL_LOCAL' if state=='VERIFICADO LOCAL' else ('BLOCKED_EXTERNAL' if state=='BLOQUEADO' else ('PARTIAL' if 'PARCIAL' in r.get('technical_status','').upper() else 'NOT_EXECUTED'))
    technical=r.get('technical_status',state); institutional='Pendiente de aprobación'; missing=r.get('dependency') or r.get('next_action') or 'Acreditar con responsable autorizado.'
    lines.append('| '+ ' | '.join(str(x).replace('|','\\|').replace('\n',' ') for x in [r['id'],r.get('positive_test','Escenario documental'),result,'Nivel C' if result=='PASS_REAL_LOCAL' else 'Nivel A/B',ev,technical,institutional,missing])+' |')
(root/'docs/anexo14/RESULTADO_SIMULACION_82.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
print('generated',len(t['rows']))
