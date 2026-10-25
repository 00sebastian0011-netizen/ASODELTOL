#!/usr/bin/env python3
"""Minimal signed GitHub webhook receiver for ASODELTOL deployments."""

from __future__ import annotations

import hashlib
import hmac
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import queue
import re
import subprocess
import threading
import time


MAX_BODY = 1024 * 1024
SHA_RE = re.compile(r"^[0-9a-f]{40}$")
DELIVERY_RE = re.compile(r"^[0-9a-fA-F-]{8,80}$")
DEPLOY_QUEUE: queue.Queue[tuple[str, str]] = queue.Queue(maxsize=1)
LAST_DELIVERY = ""
DELIVERY_LOCK = threading.Lock()


def secret() -> bytes:
    value = Path(os.environ["ASODELTOL_WEBHOOK_SECRET_FILE"]).read_bytes().strip()
    if len(value) < 32:
        raise RuntimeError("Webhook secret is missing or too short")
    return value


WEBHOOK_SECRET = secret()
EXPECTED_REPOSITORY = os.environ.get("ASODELTOL_REPOSITORY", "00sebastian0011-netizen/ASODELTOL")
EXPECTED_REF = os.environ.get("ASODELTOL_REF", "refs/heads/main")
DEPLOY_SCRIPT = os.environ.get("ASODELTOL_DEPLOY_SCRIPT", "/opt/asodeltol-deploy/deploy_release.py")
DEBOUNCE_SECONDS = float(os.environ.get("ASODELTOL_DEBOUNCE_SECONDS", "1.0"))


def enqueue(sha: str, delivery: str) -> None:
    try:
        while True:
            DEPLOY_QUEUE.get_nowait()
            DEPLOY_QUEUE.task_done()
    except queue.Empty:
        pass
    DEPLOY_QUEUE.put_nowait((sha, delivery))


def worker() -> None:
    while True:
        sha, delivery = DEPLOY_QUEUE.get()
        time.sleep(DEBOUNCE_SECONDS)
        try:
            while True:
                sha, delivery = DEPLOY_QUEUE.get_nowait()
                DEPLOY_QUEUE.task_done()
        except queue.Empty:
            pass
        started = time.monotonic()
        completed = subprocess.run(
            ["/usr/bin/python3", DEPLOY_SCRIPT, "--sha", sha],
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=180,
            check=False,
        )
        duration = round(time.monotonic() - started, 3)
        message = completed.stdout.strip().replace("\n", " ")
        print(json.dumps({
            "event": "deployment_finished",
            "delivery": delivery,
            "sha": sha,
            "exit_code": completed.returncode,
            "duration_seconds": duration,
            "result": message[-4000:],
        }, ensure_ascii=False), flush=True)
        DEPLOY_QUEUE.task_done()


class Handler(BaseHTTPRequestHandler):
    server_version = "ASODELTOLWebhook/1"

    def log_message(self, format_string: str, *args) -> None:
        print(json.dumps({
            "event": "http",
            "client": self.client_address[0],
            "message": format_string % args,
        }), flush=True)

    def respond(self, status: int, value: dict) -> None:
        body = json.dumps(value, separators=(",", ":")).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:
        if self.path != "/healthz" or self.client_address[0] not in {"127.0.0.1", "::1"}:
            self.respond(404, {"status": "not_found"})
            return
        self.respond(200, {"status": "ok"})

    def do_POST(self) -> None:
        if self.path != "/github":
            self.respond(404, {"status": "not_found"})
            return
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            self.respond(400, {"status": "invalid_length"})
            return
        if length <= 0 or length > MAX_BODY:
            self.respond(413, {"status": "invalid_size"})
            return
        body = self.rfile.read(length)
        supplied = self.headers.get("X-Hub-Signature-256", "")
        expected = "sha256=" + hmac.new(WEBHOOK_SECRET, body, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(supplied, expected):
            self.respond(401, {"status": "invalid_signature"})
            return

        event = self.headers.get("X-GitHub-Event", "")
        delivery = self.headers.get("X-GitHub-Delivery", "")
        if not DELIVERY_RE.fullmatch(delivery):
            self.respond(400, {"status": "invalid_delivery"})
            return
        try:
            payload = json.loads(body)
        except (UnicodeDecodeError, json.JSONDecodeError):
            self.respond(400, {"status": "invalid_json"})
            return
        repository = payload.get("repository", {}).get("full_name")
        if repository != EXPECTED_REPOSITORY:
            self.respond(403, {"status": "invalid_repository"})
            return
        if event == "ping":
            self.respond(200, {"status": "pong"})
            return
        if event != "push" or payload.get("ref") != EXPECTED_REF or payload.get("deleted"):
            self.respond(202, {"status": "ignored"})
            return
        sha = str(payload.get("after", "")).lower()
        if not SHA_RE.fullmatch(sha):
            self.respond(400, {"status": "invalid_sha"})
            return

        global LAST_DELIVERY
        with DELIVERY_LOCK:
            if delivery == LAST_DELIVERY:
                self.respond(202, {"status": "duplicate"})
                return
            LAST_DELIVERY = delivery
        enqueue(sha, delivery)
        self.respond(202, {"status": "queued", "sha": sha})


def main() -> None:
    threading.Thread(target=worker, name="deployment-worker", daemon=True).start()
    host = os.environ.get("ASODELTOL_LISTEN_HOST", "127.0.0.1")
    port = int(os.environ.get("ASODELTOL_LISTEN_PORT", "8765"))
    server = ThreadingHTTPServer((host, port), Handler)
    print(json.dumps({"event": "receiver_started", "host": host, "port": port}), flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
