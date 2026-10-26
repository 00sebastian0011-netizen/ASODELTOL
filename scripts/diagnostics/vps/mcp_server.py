#!/usr/bin/env python3
"""Stateless authenticated MCP and fixed read-only REST facade for ASODELTOL VPS."""
from __future__ import annotations

import hmac
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import threading
from typing import Any

from probes import TOOLS

MCP_VERSION = "2025-06-18"
MAX_BODY = 16_384
MAX_OUTPUT = 24_000
MAX_INFLIGHT = threading.BoundedSemaphore(value=8)
ALLOWED_ORIGIN = os.environ.get("ASODELTOL_DIAG_ORIGIN", "https://aulaasodeltol.online")
REST_TOOLS = {
    "/__asodeltol_diag/v1/system": "vps_system_health",
    "/__asodeltol_diag/v1/versions": "moodle_versions",
    "/__asodeltol_diag/v1/uploads": "upload_limits",
    "/__asodeltol_diag/v1/deploy": "deployment_status",
}


def load_token() -> str:
    token = Path(os.environ["ASODELTOL_DIAG_TOKEN_FILE"]).read_text(encoding="ascii").strip()
    if len(token) < 48 or any(not ((c.isascii() and c.isalnum()) or c in "-_") for c in token):
        raise RuntimeError("Diagnostic bearer token missing or invalid; generate on VPS")
    return token


TOKEN = load_token()
TOOL_DEFS = [
    {"name": name, "description": description,
     "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
     "annotations": {"readOnlyHint": True, "destructiveHint": False, "openWorldHint": False}}
    for name, (description, _) in TOOLS.items()
]


def rpc_error(request_id: Any, code: int, message: str) -> dict:
    return {"jsonrpc": "2.0", "id": request_id,
            "error": {"code": code, "message": message}}


def handle_rpc(payload: Any) -> tuple[int, dict | None]:
    if not isinstance(payload, dict) or payload.get("jsonrpc") != "2.0":
        return 400, rpc_error(None, -32600, "Invalid JSON-RPC request")
    request_id = payload.get("id")
    method = payload.get("method")
    if request_id is not None and not isinstance(request_id, (str, int)):
        return 400, rpc_error(None, -32600, "Invalid request id")
    if method == "notifications/initialized" and request_id is None:
        return 202, None
    if request_id is None or not isinstance(method, str):
        return 400, rpc_error(None, -32600, "Request id and method required")
    params = payload.get("params", {})
    if not isinstance(params, dict):
        return 200, rpc_error(request_id, -32602, "Invalid parameters")
    if method == "initialize":
        result = {
            "protocolVersion": MCP_VERSION,
            "capabilities": {"tools": {"listChanged": False}},
            "serverInfo": {"name": "asodeltol-vps-readonly", "version": "0.1.0"},
            "instructions": "Fixed sanitized read-only diagnostics; never interpret as institutional approval.",
        }
    elif method == "ping":
        result = {}
    elif method == "tools/list":
        result = {"tools": TOOL_DEFS}
    elif method == "tools/call":
        name = params.get("name")
        arguments = params.get("arguments", {})
        if not isinstance(name, str) or name not in TOOLS or arguments != {}:
            return 200, rpc_error(request_id, -32602, "Unknown tool or non-empty arguments")
        try:
            content = TOOLS[name][1]()
        except Exception:
            result = {"content": [{"type": "text", "text": "Diagnostic temporarily unavailable"}],
                      "isError": True}
        else:
            result = {"content": [{"type": "text", "text": json.dumps(content, ensure_ascii=False,
                                                                  separators=(",", ":"))}],
                      "structuredContent": content, "isError": False}
    else:
        return 200, rpc_error(request_id, -32601, "Method not found")
    return 200, {"jsonrpc": "2.0", "id": request_id, "result": result}


class Handler(BaseHTTPRequestHandler):
    server_version = "ASODELTOLDiagnostics/1"
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt: str, *args: Any) -> None:
        # No secrets, URLs, bodies, IPs or personally identifying content in application logs.
        pass

    def reply(self, code: int, payload: dict | None = None,
              extra: dict[str, str] | None = None) -> None:
        body = b"" if payload is None else json.dumps(payload, separators=(",", ":")).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        if extra:
            for name, value in extra.items():
                self.send_header(name, value)
        self.end_headers()
        if body:
            self.wfile.write(body)

    def _authorized(self) -> bool:
        return hmac.compare_digest(self.headers.get("Authorization", ""), "Bearer " + TOKEN)

    def _valid_origin(self) -> bool:
        origin = self.headers.get("Origin", "")
        return not origin or origin == ALLOWED_ORIGIN

    def do_GET(self) -> None:
        if self.path == "/healthz" and self.client_address[0] in {"127.0.0.1", "::1"}:
            self.reply(200, {"status": "healthy"})
            return
        if self.path not in REST_TOOLS:
            self.reply(404, {"error": "not_found"})
            return
        if not self._valid_origin():
            self.reply(403, {"error": "origin_denied"})
            return
        if not self._authorized():
            self.reply(401, {"error": "unauthorized"},
                       extra={"WWW-Authenticate": 'Bearer realm="asodeltol-diagnostics"'})
            return
        if not MAX_INFLIGHT.acquire(blocking=False):
            self.reply(429, {"error": "busy"})
            return
        try:
            try:
                response = TOOLS[REST_TOOLS[self.path]][1]()
            except Exception:
                self.reply(503, {"error": "temporarily_unavailable"})
                return
            if len(json.dumps(response).encode()) > MAX_OUTPUT:
                self.reply(503, {"error": "output_limit_exceeded"})
            else:
                self.reply(200, response)
        finally:
            MAX_INFLIGHT.release()

    def do_POST(self) -> None:
        if self.path != "/mcp":
            self.reply(404, {"error": "not_found"})
            return
        if not self._valid_origin():
            self.reply(403, {"error": "origin_denied"})
            return
        if not self._authorized():
            self.reply(401, {"error": "unauthorized"},
                       extra={"WWW-Authenticate": 'Bearer realm="asodeltol-diagnostics"'})
            return
        if not MAX_INFLIGHT.acquire(blocking=False):
            self.reply(429, {"error": "busy"})
            return
        try:
            try:
                length = int(self.headers.get("Content-Length", "0"))
            except (TypeError, ValueError):
                self.reply(400, {"error": "invalid_length"})
                return
            if not (0 < length <= MAX_BODY):
                self.reply(413, {"error": "invalid_size"})
                return
            data = self.rfile.read(length)
            try:
                payload = json.loads(data)
            except (ValueError, UnicodeError):
                self.reply(400, rpc_error(None, -32700, "Parse error"))
                return
            code, result = handle_rpc(payload)
            if result is not None and len(json.dumps(result).encode()) > MAX_OUTPUT:
                reqid = payload.get("id") if isinstance(payload, dict) else None
                self.reply(200, rpc_error(reqid, -32603, "Output limit exceeded"))
            else:
                self.reply(code, result)
        finally:
            MAX_INFLIGHT.release()


def main() -> None:
    port = int(os.environ.get("ASODELTOL_DIAG_PORT", "8766"))
    if not 1024 <= port <= 65535:
        raise RuntimeError("Invalid local port")
    server = ThreadingHTTPServer(("127.0.0.1", port), Handler)
    print(json.dumps({"event": "ready", "transport": "stateless_streamable_http",
                      "bind": "loopback", "port": port}), flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
