#!/usr/bin/env python3
"""Validate the explicit one-scenario-per-REQ-01..REQ-82 manifest."""
import json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
manifest = ROOT / "tests/acceptance/anexo14/scenarios.json"
trace = ROOT / "docs/anexo14/TRAZABILIDAD_82_2026-10-25.json"
data = json.loads(manifest.read_text(encoding="utf-8"))
rows = json.loads(trace.read_text(encoding="utf-8"))["rows"]
expected = {r["id"] for r in rows}
scenarios = data.get("scenarios", [])
ids = [s.get("id") for s in scenarios]
errors = []
if len(ids) != len(set(ids)): errors.append("identificadores duplicados")
if set(ids) != expected: errors.append(f"cobertura distinta: faltan={sorted(expected-set(ids))} sobran={sorted(set(ids)-expected)}")
required = ("id", "description", "evidence_level", "command", "data", "result", "evidence", "limitation", "institutional_status")
for s in scenarios:
    missing = [k for k in required if not s.get(k)]
    if missing: errors.append(f"{s.get('id')}: campos vacíos {missing}")
    if s.get("result") == "PASS" and not s.get("evidence"): errors.append(f"{s.get('id')}: PASS sin evidencia")
    if s.get("service_class") not in {"SIMULADO", "SERVICIO_REAL_DE_PRUEBAS", "SERVICIO_PRODUCTIVO_VERIFICADO", "NO_APLICA"}:
        errors.append(f"{s.get('id')}: service_class inválido")
    if s.get("institutional_status") == "ACEPTADO_FORMALMENTE": errors.append(f"{s.get('id')}: aceptación no permitida en simulación")
if errors:
    print("SCENARIOS_INVALID")
    print("\n".join(f"- {e}" for e in errors))
    sys.exit(1)
print(json.dumps({"status":"PASS", "total":len(scenarios), "ids":len(expected)}, ensure_ascii=False))
