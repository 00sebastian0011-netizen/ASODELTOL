"""Fixed, sanitized read-only VPS probes for ASODELTOL.

Reads only allowlisted technical paths and fields. It never reads Moodle
config.php, databases, credentials, learner records, generic logs or commands.
"""
from __future__ import annotations

from datetime import datetime, timezone
import json
import os
from pathlib import Path
import re
import subprocess
from typing import Any

SITE_ROOT = Path(os.environ.get("ASODELTOL_DIAG_SITE_ROOT", "/home/user/htdocs/srv2085040.hstgr.cloud"))
STATE_ROOT = Path(os.environ.get("ASODELTOL_DIAG_STATE_ROOT", "/home/user/.local/state/asodeltol-deploy"))
MOODLEDATA = Path(os.environ.get("ASODELTOL_DIAG_MOODLEDATA", "/home/user/htdocs/moodledata"))
VHOST = Path(os.environ.get("ASODELTOL_DIAG_VHOST", "/etc/nginx/sites-enabled/srv2085040.hstgr.cloud.conf"))
SHA = re.compile(r"^[0-9a-f]{40}$")
SIZE = re.compile(r"^[0-9]{1,5}[KMG]?$", re.IGNORECASE)
COMPONENTS = ("theme_rsmax", "local_asodeltolstaff")


def checked_at() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def _read_small(path: Path, limit: int = 128_000) -> str | None:
    """Fixed paths only. Never call with any path selected by an MCP caller."""
    try:
        if not path.is_file() or path.stat().st_size > limit:
            return None
        return path.read_text(encoding="utf-8", errors="replace")
    except (OSError, UnicodeError):
        return None


def _from_php(source: str | None, field: str, *, plugin: bool) -> str | None:
    if source is None:
        return None
    prefix = r"\$plugin->" if plugin else r"\$"
    pattern = prefix + re.escape(field) + r"\s*=\s*['\"]?([A-Za-z0-9._ +()-]{1,48})['\"]?\s*;"
    match = re.search(pattern, source)
    return match.group(1).strip() if match else None


def versions() -> dict[str, Any]:
    root = SITE_ROOT / "public"
    definitions = (
        ("moodle", root / "version.php", ("version", "release")),
        ("theme_rsmax", root / "theme" / "rsmax" / "version.php", ("version", "release")),
        ("local_asodeltolstaff", root / "local" / "asodeltolstaff" / "version.php", ("version", "release")),
    )
    found = {}
    for label, path, fields in definitions:
        source = _read_small(path)
        found[label] = {
            "installed_file_present": source is not None,
            **{field: _from_php(source, field, plugin=(label != "moodle")) for field in fields},
        }
    return {"checked_at": checked_at(), "source": "installed_version_files_only",
            "components": found,
            "warning": "This does not prove Moodle DB upgrades or active plugin settings."}


def _service_active(name: str) -> str:
    if name not in {"nginx", "php8.4-fpm", "asodeltol-deploy", "asodeltol-diagnostics"}:
        return "not_checked"
    try:
        process = subprocess.run(["/usr/bin/systemctl", "is-active", name],
                                 capture_output=True, text=True, timeout=3, check=False)
        state = process.stdout.strip()
        return state if state in {"active", "inactive", "failed", "activating", "deactivating"} else "unknown"
    except (OSError, subprocess.TimeoutExpired):
        return "unknown"


def _disk(path: Path) -> dict[str, Any]:
    try:
        stats = os.statvfs(path)
        return {"available": True,
                "free_bytes": stats.f_bavail * stats.f_frsize,
                "total_bytes": stats.f_blocks * stats.f_frsize}
    except OSError:
        return {"available": False}


def _memory() -> dict[str, int]:
    source = _read_small(Path("/proc/meminfo"), 16_000)
    if source is None:
        return {}
    result = {}
    for key in ("MemTotal", "MemAvailable"):
        match = re.search(r"^" + key + r":\s*(\d+)\s+kB$", source, re.MULTILINE)
        if match:
            result[key.lower() + "_bytes"] = int(match.group(1)) * 1024
    return result


def system_health() -> dict[str, Any]:
    osrelease = _read_small(Path("/etc/os-release"), 4096) or ""
    osid = re.search(r'^ID=["\']?([a-z0-9_-]+)', osrelease, re.MULTILINE)
    osversion = re.search(r'^VERSION_ID=["\']?([0-9.]+)', osrelease, re.MULTILINE)
    uptime = _read_small(Path("/proc/uptime"), 256)
    try:
        uptime_sec = int(float(uptime.split()[0])) if uptime else None
    except (IndexError, ValueError):
        uptime_sec = None
    return {"checked_at": checked_at(), "source": "vps_readonly_probe",
            "system": {"os_id": osid.group(1) if osid else None,
                       "os_version": osversion.group(1) if osversion else None,
                       "uptime_seconds": uptime_sec, **_memory()},
            "disks": {"site": _disk(SITE_ROOT), "moodledata": _disk(MOODLEDATA)},
            "services": {unit: _service_active(unit) for unit in
                         ("nginx", "php8.4-fpm", "asodeltol-deploy", "asodeltol-diagnostics")}}


def upload_limits() -> dict[str, Any]:
    """Reads static configuration strings, never asserts real authenticated upload."""
    source = _read_small(VHOST, 256_000)
    if source is None:
        return {"checked_at": checked_at(), "source": "vhost_config_unreadable", "limits": {},
                "web_php_verified": False, "moodle_authenticated_upload_verified": False}
    source = source.replace("\\n", "\n")
    patterns = {
        "nginx_client_max_body_size": r"\bclient_max_body_size\s+([0-9]{1,5}[KMG]?)\s*;",
        "php_upload_max_filesize": r"\bupload_max_filesize\s*=\s*([0-9]{1,5}[KMG]?)\b",
        "php_post_max_size": r"\bpost_max_size\s*=\s*([0-9]{1,5}[KMG]?)\b",
    }
    values = {}
    for key, pattern in patterns.items():
        values[key] = sorted({item.upper() for item in re.findall(pattern, source, re.IGNORECASE)
                              if SIZE.fullmatch(item)})
    return {"checked_at": checked_at(), "source": "static_vhost_configuration_only",
            "limits": values, "web_php_verified": False,
            "moodle_authenticated_upload_verified": False,
            "warning": "Vhost values may be overridden in other NGINX/PHP-FPM locations."}


def _safe_sha(value: Any) -> str | None:
    return value if isinstance(value, str) and SHA.fullmatch(value) else None


def _safe_time(value: Any) -> str | None:
    if isinstance(value, str) and re.fullmatch(r"[0-9TZ:+.\-]{10,40}", value):
        return value
    return None


def deploy_status() -> dict[str, Any]:
    components = {}
    for name in COMPONENTS:
        source = _read_small(STATE_ROOT / "components" / (name + ".json"), 600_000)
        try:
            state = json.loads(source) if source else {}
        except json.JSONDecodeError:
            state = {}
        status = state.get("last_status")
        components[name] = {
            "state_present": bool(state),
            "base_sha": _safe_sha(state.get("base_sha")),
            "last_status": status if status in
                {"deployed", "no_changes", "manual_required", "conflict", "skipped", "partial"} else None,
            "protected_files_count": len(state["protected"]) if isinstance(state.get("protected"), list) else None,
            "last_processed_at": _safe_time(state.get("last_processed_at")),
        }
    source = _read_small(STATE_ROOT / "last-result.json", 200_000)
    try:
        last = json.loads(source) if source else {}
    except json.JSONDecodeError:
        last = {}
    status = last.get("status")
    return {"checked_at": checked_at(), "source": "local_deployment_agent_state",
            "components": components,
            "last_release": {"present": bool(last),
                             "status": status if status in
                                {"deployed", "no_changes", "manual_required", "conflict", "partial"} else None,
                             "requested_sha": _safe_sha(last.get("requested_sha")),
                             "completed_at": _safe_time(last.get("completed_at"))},
            "warning": "Agent state alone is not an authenticated production smoke test."}


TOOLS = {
    "vps_system_health": ("Sanitized OS, uptime, disk and fixed service states; no logs or users.", system_health),
    "moodle_versions": ("Installed version files only, not live Moodle database state.", versions),
    "upload_limits": ("Static NGINX/PHP vhost limit hints; effective upload unverified.", upload_limits),
    "deployment_status": ("Sanitized GitHub deploy agent state, no file paths or contents.", deploy_status),
}
