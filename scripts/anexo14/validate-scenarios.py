#!/usr/bin/env python3
"""Validate the explicit one-scenario-per-REQ-01..REQ-82 manifest."""
import json, sys, hashlib
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
required = ("id", "description", "evidence_level", "command", "data", "result", "evidence", "limitation", "institutional_status", "candidate_version")
allowed = {"PASS_REAL_LOCAL", "PASS_SIMULATED", "PARTIAL", "FAIL", "BLOCKED_EXTERNAL", "NOT_EXECUTED"}
for s in scenarios:
    missing = [k for k in required if not s.get(k)]
    if missing: errors.append(f"{s.get('id')}: campos vacíos {missing}")
    if s.get("result") not in allowed: errors.append(f"{s.get('id')}: resultado inválido {s.get('result')}")
    if s.get("result") in {"PASS_REAL_LOCAL", "PASS_SIMULATED"} and not s.get("evidence"): errors.append(f"{s.get('id')}: PASS sin evidencia")
    if s.get("result") in {"PASS_REAL_LOCAL", "PASS_SIMULATED", "PARTIAL"} and not s.get("executed_at"): errors.append(f"{s.get('id')}: ejecución sin fecha")
    for path, digest in (s.get("evidence_sha256") or {}).items():
        f = ROOT / path
        if not f.is_file(): errors.append(f"{s.get('id')}: evidencia ausente {path}")
        elif hashlib.sha256(f.read_bytes()).hexdigest() != digest: errors.append(f"{s.get('id')}: hash alterado {path}")
    if s.get("service_class") not in {"SIMULADO", "SERVICIO_REAL_DE_PRUEBAS", "SERVICIO_PRODUCTIVO_VERIFICADO", "NO_APLICA"}:
        errors.append(f"{s.get('id')}: service_class inválido")
    if s.get("institutional_status") == "ACEPTADO_FORMALMENTE": errors.append(f"{s.get('id')}: aceptación no permitida en simulación")
if errors:
    print("SCENARIOS_INVALID")
    print("\n".join(f"- {e}" for e in errors))
    sys.exit(1)
print(json.dumps({"status":"PASS", "total":len(scenarios), "ids":len(expected)}, ensure_ascii=False))
