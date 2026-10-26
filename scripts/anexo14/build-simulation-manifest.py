#!/usr/bin/env python3
"""Build the explicit 82-scenario manifest from the canonical traceability rows."""
import json
from pathlib import Path
root = Path(__file__).resolve().parents[2]
trace = json.loads((root/"docs/anexo14/TRAZABILIDAD_82_2026-10-25.json").read_text(encoding="utf-8"))
out=[]
for r in trace["rows"]:
    state = r["state"]
    result = "PASS" if state == "VERIFICADO LOCAL" else ("PARTIAL" if "PARCIAL" in r.get("technical_status", "").upper() else "NOT_EXECUTED")
    if state == "BLOQUEADO": result = "BLOCKED"
    evidence = r.get("evidence", [])
    if isinstance(evidence, str): evidence = [evidence]
    out.append({
        "id": r["id"], "description": r["criterion"],
        "evidence_level": "C" if result in {"PASS","PARTIAL"} else "A/B",
        "command": "; ".join(r.get("files", [])) or "documental",
        "data": "datos sintéticos aislados de close19; contenido oficial excluido",
        "result": result, "evidence": evidence,
        "service_class": "SERVICIO_REAL_DE_PRUEBAS" if result == "PASS" else "NO_APLICA",
        "limitation": r.get("dependency") or r.get("next_action") or "No acreditación institucional.",
        "institutional_status": "PENDIENTE_DE_APROBACION",
        "technical_status": r.get("technical_status", state),
        "commit": r.get("commit"), "environment": r.get("environment")
    })
payload={"schema_version":"1.0","source":"docs/anexo14/TRAZABILIDAD_82_2026-10-25.json","environment":"staging-local-close19","scenarios":out}
(root/"tests/acceptance/anexo14/scenarios.json").write_text(json.dumps(payload,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
print(f"generated {len(out)} scenarios")
