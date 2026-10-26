"""Durable local-only Docker monitor, with no credentials or application data mount."""
import select
import socket
import socketserver
import sys
import threading

import monitor


class Relay(socketserver.BaseRequestHandler):
    def handle(self):
        with socket.create_connection(('asodeltol-close19-webserver-1', 80), timeout=60) as upstream:
            peers = [self.request, upstream]
            while True:
                readable, _, _ = select.select(peers, [], [], 60)
                if not readable:
                    return
                for source in readable:
                    chunk = source.recv(65536)
                    if not chunk:
                        return
                    (upstream if source is self.request else self.request).sendall(chunk)


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if __name__ == '__main__':
    with Server(('127.0.0.1', 8054), Relay) as server:
        threading.Thread(target=server.serve_forever, daemon=True).start()
        sys.argv = [__file__, '--base', 'http://127.0.0.1:8054', '--output', '/evidence/availability.jsonl',
                    '--interval', '3600', '--count', '0']
        raise SystemExit(monitor.main())
