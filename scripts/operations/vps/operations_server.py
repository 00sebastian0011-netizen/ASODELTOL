#!/usr/bin/env python3
"""Loopback-only, allowlisted operational MCP for ASODELTOL Moodle.

It deliberately has no shell tool.  Every state-changing call requires a
root-issued approval file whose operation and parameters are checked here.
"""
from __future__ import annotations

import hashlib
import hmac
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import re
import subprocess
import threading
import time
from typing import Any

MAX_BODY = 16_384
MAX_OUTPUT = 12_000
SHA = re.compile(r"^[0-9a-f]{40}$")
OPS = {"backup", "staff_deploy_0600", "moodle_upgrade", "policy_publish", "policy_verify", "rollback"}
TOKEN_FILE = Path(os.environ["ASODELTOL_OPS_TOKEN_FILE"])
APPROVALS = Path(os.environ["ASODELTOL_OPS_APPROVAL_DIR"])
STATE = Path(os.environ["ASODELTOL_OPS_STATE_DIR"])
ROOT = Path(os.environ["ASODELTOL_OPS_ROOT"])
SITE = Path(os.environ["ASODELTOL_SITE_ROOT"])
REPO = Path(os.environ["ASODELTOL_REPO_DIR"])
DEPLOY_STATE = Path(os.environ["ASODELTOL_DEPLOY_STATE_DIR"])
PHP = os.environ.get("ASODELTOL_PHP", "/usr/bin/php8.4")
DEPLOY = ROOT / "deploy_release.py"
TOKEN = TOKEN_FILE.read_text(encoding="ascii").strip()
LOCK = threading.BoundedSemaphore(1)

TOOLS = {
    "operations_backup": ("backup", {"label": {"type": "string", "maxLength": 80}}),
    "staff_deploy_0600": ("staff_deploy_0600", {"sha": {"type": "string", "pattern": "^[0-9a-f]{40}$"}}),
    "moodle_upgrade": ("moodle_upgrade", {}),
    "policy_publish": ("policy_publish", {}),
    "policy_verify": ("policy_verify", {}),
    "operations_rollback": ("rollback", {"backup_id": {"type": "string", "pattern": "^[A-Za-z0-9_.-]{1,100}$"}}),
}


def compact(value: str) -> str:
    return value[-MAX_OUTPUT:].replace(str(TOKEN), "[redacted]")


def run(command: list[str], timeout: int = 300) -> dict[str, Any]:
    result = subprocess.run(command, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                            stderr=subprocess.STDOUT, text=True, timeout=timeout, check=False)
    return {"exit_code": result.returncode, "output": compact(result.stdout)}


def approval(operation: str, authorization_id: str, parameters: dict[str, Any]) -> None:
    if not re.fullmatch(r"[A-Za-z0-9_.-]{16,100}", authorization_id):
        raise ValueError("invalid_authorization_id")
    path = APPROVALS / (authorization_id + ".json")
    data = json.loads(path.read_text(encoding="utf-8"))
    stat = path.stat()
    if stat.st_uid != 0 or stat.st_mode & 0o022:
        raise ValueError("unsafe_authorization_file")
    if data.get("operation") != operation or data.get("parameters") != parameters:
        raise ValueError("authorization_scope_mismatch")
    if not isinstance(data.get("expires_at"), int) or data["expires_at"] < int(time.time()):
        raise ValueError("authorization_expired")
    STATE.mkdir(parents=True, exist_ok=True)
    used = STATE / "used-authorizations.json"
    entries = json.loads(used.read_text(encoding="utf-8")) if used.exists() else []
    if authorization_id in entries:
        raise ValueError("authorization_already_used")
    entries.append(authorization_id)
    temp = used.with_suffix(".tmp")
    temp.write_text(json.dumps(entries[-1000:]), encoding="utf-8")
    os.replace(temp, used)


def validate_staff_sha(sha: str) -> None:
    if not SHA.fullmatch(sha):
        raise ValueError("invalid_sha")
    ancestor = run(["git", "-C", str(REPO), "merge-base", "--is-ancestor", sha, "refs/remotes/origin/main"])
    version = run(["git", "-C", str(REPO), "show", f"{sha}:plugins/local/asodeltolstaff/version.php"])
    if ancestor["exit_code"] or version["exit_code"] or "release = '0.6.0'" not in version["output"]:
        raise ValueError("staff_0600_commit_not_authorized")


def execute(operation: str, parameters: dict[str, Any]) -> dict[str, Any]:
    if operation == "backup":
        return run([PHP, str(ROOT / "backup.php"), parameters.get("label", "manual")], 900)
    if operation == "staff_deploy_0600":
        sha = parameters["sha"]
        validate_staff_sha(sha)
        before = run(["git", "-C", str(REPO), "rev-parse", f"{sha}^1"])
        if before["exit_code"]:
            return before
        return run(["/usr/bin/python3", str(DEPLOY), "--before", before["output"].strip(), "--sha", sha,
                    "--allow-critical-component", "local_asodeltolstaff"], 600)
    if operation == "moodle_upgrade":
        return run([PHP, str(SITE / "admin/cli/upgrade.php"), "--non-interactive"], 900)
    if operation == "policy_publish":
        return run([PHP, str(ROOT / "policy_publish.php")], 300)
    if operation == "policy_verify":
        return run([PHP, str(ROOT / "policy_verify.php")], 120)
    if operation == "rollback":
        return run([PHP, str(ROOT / "rollback.php"), parameters["backup_id"]], 900)
    raise ValueError("unknown_operation")


def rpc(payload: Any) -> tuple[int, dict[str, Any] | None]:
    if not isinstance(payload, dict) or payload.get("jsonrpc") != "2.0":
        return 400, {"jsonrpc": "2.0", "id": None, "error": {"code": -32600, "message": "invalid_request"}}
    ident, method, params = payload.get("id"), payload.get("method"), payload.get("params", {})
    if method == "notifications/initialized" and ident is None:
        return 202, None
    if not isinstance(ident, (str, int)) or not isinstance(params, dict):
        return 400, {"jsonrpc": "2.0", "id": None, "error": {"code": -32600, "message": "invalid_request"}}
    if method == "initialize":
        result = {"protocolVersion": "2025-06-18", "capabilities": {"tools": {"listChanged": False}},
                  "serverInfo": {"name": "asodeltol-vps-operations", "version": "1.0.0"},
                  "instructions": "Closed operational tools only; each call requires a root-issued authorization id."}
    elif method == "tools/list":
        result = {"tools": [{"name": name, "description": op,
                  "inputSchema": {"type": "object", "properties": {**schema, "authorization_id": {"type": "string"}},
                                  "required": ["authorization_id", *schema.keys()], "additionalProperties": False},
                  "annotations": {"readOnlyHint": False, "destructiveHint": op != "policy_verify", "openWorldHint": False}}
                 for name, (op, schema) in TOOLS.items()]}
    elif method == "tools/call":
        name, args = params.get("name"), params.get("arguments")
        if name not in TOOLS or not isinstance(args, dict):
            return 200, {"jsonrpc": "2.0", "id": ident, "error": {"code": -32602, "message": "invalid_tool"}}
        operation, schema = TOOLS[name]
        if set(args) != set(schema) | {"authorization_id"}:
            return 200, {"jsonrpc": "2.0", "id": ident, "error": {"code": -32602, "message": "invalid_arguments"}}
        try:
            if not LOCK.acquire(blocking=False):
                raise ValueError("operation_in_progress")
            try:
                approval(operation, args["authorization_id"], {key: args[key] for key in schema})
                data = execute(operation, {key: args[key] for key in schema})
            finally:
                LOCK.release()
        except (OSError, ValueError, json.JSONDecodeError, subprocess.TimeoutExpired) as error:
            result = {"content": [{"type": "text", "text": str(error)}], "isError": True}
        else:
            result = {"content": [{"type": "text", "text": json.dumps(data, ensure_ascii=False)}],
                      "structuredContent": data, "isError": data["exit_code"] != 0}
    else:
        return 200, {"jsonrpc": "2.0", "id": ident, "error": {"code": -32601, "message": "method_not_found"}}
    return 200, {"jsonrpc": "2.0", "id": ident, "result": result}


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *_: Any) -> None: pass
    def reply(self, code: int, body: dict[str, Any] | None) -> None:
        raw = b"" if body is None else json.dumps(body, separators=(",", ":")).encode()
        self.send_response(code); self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(raw))); self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff"); self.end_headers()
        if raw: self.wfile.write(raw)
    def do_GET(self) -> None:
        self.reply(200, {"status": "healthy"}) if self.path == "/healthz" and self.client_address[0] in {"127.0.0.1", "::1"} else self.reply(404, {"error": "not_found"})
    def do_POST(self) -> None:
        if self.path != "/mcp" or not hmac.compare_digest(self.headers.get("Authorization", ""), "Bearer " + TOKEN): self.reply(401, {"error": "unauthorized"}); return
        try: size = int(self.headers.get("Content-Length", "0")); payload = json.loads(self.rfile.read(size)) if 0 < size <= MAX_BODY else None
        except (ValueError, UnicodeError): payload = None
        code, response = rpc(payload); self.reply(code, response)


if __name__ == "__main__":
    ThreadingHTTPServer(("127.0.0.1", int(os.environ.get("ASODELTOL_OPS_PORT", "8767"))), Handler).serve_forever()
