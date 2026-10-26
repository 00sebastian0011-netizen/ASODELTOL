"""Validated, bounded validation queue used by the private ASODELTOL MCP.

This module never launches processes, opens a browser, reads credentials, or
returns evidence bytes. The separate QA executor consumes the JSON jobs.
"""
from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import threading
import time
from typing import Any


MAX_CASES = 5
MAX_RESULTS = 5
DEDUPE_SECONDS = 300
ID_RE = re.compile(r"^[A-Z0-9][A-Z0-9._-]{1,63}$")
VALIDATION_ID_RE = re.compile(r"^val-[a-f0-9]{16}$")
VALIDATION_TOOLS = {
    "validation_catalog": {
        "description": "List registered validation cases or compact cases matching requirement IDs.",
        "inputSchema": {
            "type": "object",
            "properties": {
                "requirement_ids": {"type": "array", "items": {"type": "string"}, "maxItems": MAX_CASES},
                "case_ids": {"type": "array", "items": {"type": "string"}, "maxItems": MAX_CASES},
                "limit": {"type": "integer", "minimum": 1, "maximum": MAX_RESULTS},
            },
            "additionalProperties": False,
        },
    },
    "run_validation": {
        "description": "Queue up to five registered read-only validation cases for the separate QA executor.",
        "inputSchema": {
            "type": "object",
            "required": ["case_ids"],
            "properties": {
                "case_ids": {"type": "array", "items": {"type": "string"}, "minItems": 1, "maxItems": MAX_CASES},
                "mode": {"type": "string", "enum": ["read_only"]},
            },
            "additionalProperties": False,
        },
    },
    "validation_status": {
        "description": "Return compact status for one validation request.",
        "inputSchema": {"type": "object", "required": ["validation_id"], "properties": {"validation_id": {"type": "string"}}, "additionalProperties": False},
    },
    "validation_results": {
        "description": "Return compact sanitized results, optionally filtered by requirement ID.",
        "inputSchema": {
            "type": "object",
            "required": ["validation_id"],
            "properties": {
                "validation_id": {"type": "string"},
                "requirement_ids": {"type": "array", "items": {"type": "string"}, "maxItems": MAX_CASES},
                "limit": {"type": "integer", "minimum": 1, "maximum": MAX_RESULTS},
            },
            "additionalProperties": False,
        },
    },
    "validation_evidence": {
        "description": "Return sanitized evidence metadata only; never evidence bytes, cookies, tokens, or credentials.",
        "inputSchema": {
            "type": "object",
            "required": ["validation_id"],
            "properties": {"validation_id": {"type": "string"}, "case_id": {"type": "string"}, "limit": {"type": "integer", "minimum": 1, "maximum": MAX_RESULTS}},
            "additionalProperties": False,
        },
    },
}


class ValidationInputError(ValueError):
    """Safe client error; no secret or filesystem detail is included."""


class ValidationUnavailable(RuntimeError):
    """The service cannot operate with its configured local state."""


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _id(value: Any, label: str) -> str:
    if not isinstance(value, str) or not ID_RE.fullmatch(value):
        raise ValidationInputError(f"Invalid {label}")
    return value


def _ids(value: Any, label: str, *, maximum: int = MAX_CASES) -> list[str]:
    if not isinstance(value, list) or not value or len(value) > maximum:
        raise ValidationInputError(f"Invalid {label}")
    result = [_id(item, label) for item in value]
    if len(set(result)) != len(result):
        raise ValidationInputError(f"Duplicate {label}")
    return result


def _validation_id(value: Any) -> str:
    if not isinstance(value, str) or not VALIDATION_ID_RE.fullmatch(value):
        raise ValidationInputError("Invalid validation_id")
    return value


def _limit(value: Any) -> int:
    if value is None:
        return MAX_RESULTS
    if not isinstance(value, int) or isinstance(value, bool) or not 1 <= value <= MAX_RESULTS:
        raise ValidationInputError("Invalid limit")
    return value


def _safe_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


class ValidationService:
    """Small filesystem-backed queue with deduplication and bounded output."""

    def __init__(self, catalog_file: str | Path | None = None, state_dir: str | Path | None = None) -> None:
        catalog_value = catalog_file or os.environ.get("ASODELTOL_VALIDATION_CATALOG_FILE")
        self.catalog_file = Path(catalog_value) if catalog_value else Path(__file__).resolve().parents[3] / "docs/anexo14/qa/production-test-catalog.json"
        state_value = state_dir or os.environ.get("ASODELTOL_VALIDATION_STATE_DIR")
        self.state_dir = Path(state_value) if state_value else Path(__file__).resolve().parents[3] / "build/anexo14/validation-state"
        self.jobs_dir = self.state_dir / "jobs"
        self.audit_file = self.state_dir / "audit.jsonl"
        self._lock = threading.RLock()
        self._catalog: dict[str, Any] | None = None

    def _load_catalog(self) -> dict[str, Any]:
        if self._catalog is not None:
            return self._catalog
        try:
            data = json.loads(self.catalog_file.read_text(encoding="utf-8"))
        except (OSError, ValueError) as exc:
            raise ValidationUnavailable("Validation catalog unavailable") from exc
        cases = data.get("cases") if isinstance(data, dict) else None
        if not isinstance(cases, list) or not cases:
            raise ValidationUnavailable("Validation catalog invalid")
        index: dict[str, Any] = {}
        for case in cases:
            if not isinstance(case, dict) or not ID_RE.fullmatch(str(case.get("case_id", ""))):
                raise ValidationUnavailable("Validation catalog invalid")
            case_id = case["case_id"]
            if case_id in index or not isinstance(case.get("requirements"), list) or not case["requirements"]:
                raise ValidationUnavailable("Validation catalog invalid")
            index[case_id] = case
        self._catalog = {"schema_version": data.get("schema_version"), "cases": index, "prohibited": data.get("prohibited", [])}
        return self._catalog

    def _ensure_state(self) -> None:
        self.jobs_dir.mkdir(parents=True, exist_ok=True)
        try:
            self.state_dir.chmod(0o700)
            self.jobs_dir.chmod(0o700)
        except OSError:
            pass

    def _audit(self, event: str, **fields: Any) -> None:
        self._ensure_state()
        record = {"at": _now(), "event": event, **{key: fields[key] for key in fields if key in {"validation_id", "case_ids", "status", "reason"}}}
        with self.audit_file.open("a", encoding="utf-8") as handle:
            handle.write(_safe_json(record) + "\n")
        try:
            self.audit_file.chmod(0o600)
        except OSError:
            pass

    def _job_path(self, validation_id: str) -> Path:
        return self.jobs_dir / f"{validation_id}.json"

    def _read_job(self, validation_id: str) -> dict[str, Any]:
        _validation_id(validation_id)
        try:
            job = json.loads(self._job_path(validation_id).read_text(encoding="utf-8"))
        except (OSError, ValueError) as exc:
            raise ValidationInputError("Unknown validation_id") from exc
        if not isinstance(job, dict) or job.get("validation_id") != validation_id:
            raise ValidationInputError("Unknown validation_id")
        return job

    def _write_job(self, job: dict[str, Any]) -> None:
        self._ensure_state()
        target = self._job_path(job["validation_id"])
        temporary = target.with_suffix(".tmp")
        temporary.write_text(_safe_json(job) + "\n", encoding="utf-8")
        try:
            temporary.chmod(0o600)
        except OSError:
            pass
        os.replace(temporary, target)

    def _case_summary(self, case: dict[str, Any]) -> dict[str, Any]:
        risk = case.get("risk") or ("destructive_requires_authorization" if case.get("kind") == "upload" else "non_destructive")
        return {
            "case_id": case["case_id"],
            "requirements": case["requirements"],
            "kind": case.get("kind"),
            "risk": risk,
            "authorized_action": str(case.get("authorized_action", ""))[:220],
        }

    def catalog(self, args: dict[str, Any]) -> dict[str, Any]:
        allowed = {"requirement_ids", "case_ids", "limit"}
        if set(args) - allowed:
            raise ValidationInputError("Unsupported catalog parameters")
        data = self._load_catalog()
        wanted_cases = [_id(value, "case_id") for value in args.get("case_ids", [])] if args.get("case_ids") is not None else None
        wanted_reqs = [_id(value, "requirement_id") for value in args.get("requirement_ids", [])] if args.get("requirement_ids") is not None else None
        if wanted_cases is not None and len(wanted_cases) > MAX_CASES:
            raise ValidationInputError("Too many case_ids")
        if wanted_reqs is not None and len(wanted_reqs) > MAX_CASES:
            raise ValidationInputError("Too many requirement_ids")
        selected = list(data["cases"].values())
        if wanted_cases is not None:
            selected = [data["cases"][case_id] for case_id in wanted_cases if case_id in data["cases"]]
            if len(selected) != len(wanted_cases):
                raise ValidationInputError("Unknown case_id")
        if wanted_reqs is not None:
            selected = [case for case in selected if any(req in wanted_reqs for req in case["requirements"])]
        selected = selected[:_limit(args.get("limit"))]
        return {"schema_version": data["schema_version"], "count": len(selected), "cases": [self._case_summary(case) for case in selected]}

    def run(self, args: dict[str, Any]) -> dict[str, Any]:
        if set(args) - {"case_ids", "mode"}:
            raise ValidationInputError("Unsupported run_validation parameters")
        case_ids = _ids(args.get("case_ids"), "case_ids")
        if args.get("mode", "read_only") != "read_only":
            raise ValidationInputError("Only read_only mode is supported")
        catalog = self._load_catalog()["cases"]
        unknown = [case_id for case_id in case_ids if case_id not in catalog]
        if unknown:
            raise ValidationInputError("Unknown case_id")
        signature = hashlib.sha256(_safe_json(sorted(case_ids)).encode()).hexdigest()
        now = time.time()
        with self._lock:
            self._ensure_state()
            for path in sorted(self.jobs_dir.glob("val-*.json")):
                try:
                    previous = json.loads(path.read_text(encoding="utf-8"))
                except (OSError, ValueError):
                    continue
                if previous.get("signature") == signature and now - float(previous.get("created_epoch", 0)) < DEDUPE_SECONDS:
                    self._audit("deduplicated", validation_id=previous.get("validation_id"), case_ids=case_ids, status=previous.get("status"))
                    return self._compact_status(previous)
            validation_id = "val-" + secrets.token_hex(8)
            blocked = [
                {"case_id": case_id, "requirements": catalog[case_id]["requirements"], "status": "BLOCKED", "reason": "Requires later authorization, isolation, backup and recovery"}
                for case_id in case_ids if (catalog[case_id].get("risk") or ("destructive_requires_authorization" if catalog[case_id].get("kind") == "upload" else "non_destructive")) != "non_destructive"
            ]
            status = "BLOCKED" if len(blocked) == len(case_ids) else "QUEUED"
            job = {
                "validation_id": validation_id,
                "signature": signature,
                "created_epoch": now,
                "created_at": _now(),
                "mode": "read_only",
                "case_ids": case_ids,
                "status": status,
                "results": blocked,
                "evidence": [],
                "executor": {"method": "pending", "version": None},
            }
            self._write_job(job)
            self._audit("requested", validation_id=validation_id, case_ids=case_ids, status=status)
            return self._compact_status(job)

    def _compact_status(self, job: dict[str, Any]) -> dict[str, Any]:
        return {
            "validation_id": job["validation_id"],
            "status": job.get("status"),
            "created_at": job.get("created_at"),
            "case_ids": job.get("case_ids", [])[:MAX_CASES],
            "counts": {status: sum(1 for result in job.get("results", []) if result.get("status") == status) for status in ("PASS", "FAIL", "BLOCKED")},
            "executor_method": job.get("executor", {}).get("method"),
        }

    def status(self, args: dict[str, Any]) -> dict[str, Any]:
        if set(args) != {"validation_id"}:
            raise ValidationInputError("Invalid validation_status parameters")
        return self._compact_status(self._read_job(args["validation_id"]))

    def results(self, args: dict[str, Any]) -> dict[str, Any]:
        if set(args) - {"validation_id", "requirement_ids", "limit"} or "validation_id" not in args:
            raise ValidationInputError("Invalid validation_results parameters")
        job = self._read_job(args["validation_id"])
        requirement_ids = args.get("requirement_ids")
        if requirement_ids is not None:
            requirement_ids = _ids(requirement_ids, "requirement_ids")
        results = job.get("results", [])
        if requirement_ids:
            results = [result for result in results if any(req in requirement_ids for req in result.get("requirements", []))]
        return {"validation_id": job["validation_id"], "status": job.get("status"), "count": len(results[:_limit(args.get("limit"))]), "results": results[:_limit(args.get("limit"))]}

    def evidence(self, args: dict[str, Any]) -> dict[str, Any]:
        if set(args) - {"validation_id", "case_id", "limit"} or "validation_id" not in args:
            raise ValidationInputError("Invalid validation_evidence parameters")
        job = self._read_job(args["validation_id"])
        case_id = _id(args["case_id"], "case_id") if args.get("case_id") is not None else None
        evidence = job.get("evidence", [])
        if case_id:
            evidence = [item for item in evidence if item.get("case_id") == case_id]
        safe = []
        for item in evidence[:_limit(args.get("limit"))]:
            safe.append({key: item[key] for key in ("evidence_id", "case_id", "status", "method", "sha256", "bytes", "generated_at", "limitation") if key in item})
        return {"validation_id": job["validation_id"], "count": len(safe), "evidence": safe, "detail_available": bool(safe)}

    def dispatch(self, name: str, args: Any) -> dict[str, Any]:
        if not isinstance(args, dict):
            raise ValidationInputError("Arguments must be an object")
        return {
            "validation_catalog": self.catalog,
            "run_validation": self.run,
            "validation_status": self.status,
            "validation_results": self.results,
            "validation_evidence": self.evidence,
        }[name](args)
