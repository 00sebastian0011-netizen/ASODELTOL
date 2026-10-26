#!/usr/bin/env python3
"""Incremental, drift-aware deployment for the ASODELTOL Moodle components."""

from __future__ import annotations

import argparse
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tempfile
import time
from typing import Any


SHA_RE = re.compile(r"^[0-9a-f]{40}$")
ZERO_SHA = "0" * 40
RISK_ORDER = {"none": 0, "fast": 1, "standard": 2, "critical": 3}


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).isoformat(timespec="milliseconds")


def run(command: list[str], *, cwd: Path | None = None, text: bool = True,
        check: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(
        command,
        cwd=cwd,
        text=text,
        check=check,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )


def git(repo: Path, *args: str, binary: bool = False) -> bytes | str:
    completed = run(["git", *args], cwd=repo, text=not binary)
    return completed.stdout


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def safe_relative(value: str) -> PurePosixPath:
    relative = PurePosixPath(value)
    if relative.is_absolute() or not relative.parts or ".." in relative.parts:
        raise RuntimeError(f"Unsafe repository path: {value}")
    return relative


def matches(relative: str, patterns: list[str]) -> bool:
    return any(relative == pattern or relative.startswith(pattern) for pattern in patterns)


def atomic_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(f".{path.name}.{os.getpid()}.tmp")
    temporary.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    os.replace(temporary, path)


def scan_directory(root: Path, excluded: list[str]) -> tuple[dict[str, str], list[str]]:
    hashes: dict[str, str] = {}
    symlinks: list[str] = []
    if not root.exists():
        return hashes, symlinks
    for path in sorted(root.rglob("*")):
        relative = path.relative_to(root).as_posix()
        if matches(relative, excluded):
            continue
        if path.is_symlink():
            symlinks.append(relative)
        elif path.is_file():
            hashes[relative] = sha256_file(path)
    return hashes, symlinks


def git_tree(repo: Path, commit: str, source: str, excluded: list[str]) -> dict[str, dict[str, str]]:
    output = git(repo, "ls-tree", "-r", "-z", commit, "--", source, binary=True)
    assert isinstance(output, bytes)
    entries: dict[str, dict[str, str]] = {}
    prefix = source.rstrip("/") + "/"
    for raw in output.split(b"\0"):
        if not raw:
            continue
        metadata, raw_path = raw.split(b"\t", 1)
        mode, object_type, object_sha = metadata.decode("ascii").split(" ")
        repository_path = raw_path.decode("utf-8")
        if not repository_path.startswith(prefix):
            continue
        relative = repository_path[len(prefix):]
        safe_relative(relative)
        if matches(relative, excluded):
            continue
        if object_type != "blob" or mode == "120000":
            raise RuntimeError(f"Unsupported Git entry in {source}: {repository_path} ({mode} {object_type})")
        content = git(repo, "show", f"{commit}:{repository_path}", binary=True)
        assert isinstance(content, bytes)
        entries[relative] = {"mode": mode, "sha256": sha256_bytes(content)}
    return entries


def changed_paths(repo: Path, base: str, target: str, source: str,
                  excluded: list[str]) -> dict[str, str]:
    output = git(repo, "diff", "--name-status", "-z", "--no-renames", base, target,
                 "--", source, binary=True)
    assert isinstance(output, bytes)
    tokens = output.split(b"\0")
    changes: dict[str, str] = {}
    prefix = source.rstrip("/") + "/"
    index = 0
    while index + 1 < len(tokens) and tokens[index]:
        status = tokens[index].decode("ascii")[0]
        repository_path = tokens[index + 1].decode("utf-8")
        index += 2
        if not repository_path.startswith(prefix):
            continue
        relative = repository_path[len(prefix):]
        safe_relative(relative)
        if matches(relative, excluded):
            continue
        changes[relative] = status
    return changes


def classify(component: dict[str, Any], paths: dict[str, str]) -> tuple[str, list[str]]:
    risk = "none"
    reasons: list[str] = []
    for relative in paths:
        item_risk = "standard"
        if matches(relative, component["critical"]):
            item_risk = "critical"
            reasons.append(f"{relative}: critical policy path")
        elif matches(relative, component["fast"]):
            item_risk = "fast"
        if RISK_ORDER[item_risk] > RISK_ORDER[risk]:
            risk = item_risk

    source_modules = [path for path in paths if path.startswith("amd/src/") and path.endswith(".js")]
    for source in source_modules:
        module = PurePosixPath(source).stem
        compiled = f"amd/build/{module}.min.js"
        if compiled not in paths:
            risk = "critical"
            reasons.append(f"{source}: missing compiled asset {compiled}")
    return risk, reasons


def state_path(state_root: Path, component_id: str) -> Path:
    return state_root / "components" / f"{component_id}.json"


def initialise(repo: Path, site: Path, state_root: Path, policy: dict[str, Any], commit: str) -> dict[str, Any]:
    summary: dict[str, Any] = {"commit": commit, "components": {}}
    for component in policy["components"]:
        target = site / component["target"]
        current, symlinks = scan_directory(target, component["excluded"])
        if symlinks:
            raise RuntimeError(f"Symlinks are not allowed in {component['id']}: {symlinks[:5]}")
        repository = git_tree(repo, commit, component["source"], component["excluded"])
        all_paths = sorted(set(current) | set(repository))
        protected = [
            item for item in all_paths
            if current.get(item) != repository.get(item, {}).get("sha256")
        ]
        component_state = {
            "component": component["id"],
            "base_sha": commit,
            "expected": current,
            "protected": protected,
            "initialized_at": utc_now(),
        }
        atomic_json(state_path(state_root, component["id"]), component_state)
        summary["components"][component["id"]] = {
            "production_files": len(current),
            "repository_files": len(repository),
            "protected_differences": len(protected),
        }
    atomic_json(state_root / "initialized.json", {**summary, "initialized_at": utc_now()})
    return summary


def load_component_state(state_root: Path, component_id: str) -> dict[str, Any]:
    path = state_path(state_root, component_id)
    if not path.is_file():
        raise RuntimeError(f"Component state has not been initialized: {component_id}")
    return json.loads(path.read_text(encoding="utf-8"))


def write_blob(repo: Path, commit: str, repository_path: str, destination: Path, mode: str) -> None:
    content = git(repo, "show", f"{commit}:{repository_path}", binary=True)
    assert isinstance(content, bytes)
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_bytes(content)
    destination.chmod(0o755 if mode == "100755" else 0o644)


def validate_stage(stage: Path, changed: dict[str, str], php_binary: str) -> None:
    _, symlinks = scan_directory(stage, [])
    if symlinks:
        raise RuntimeError(f"Staged component contains symlinks: {symlinks[:5]}")
    for relative, status in changed.items():
        if status == "D" or not relative.endswith(".php"):
            continue
        completed = run([php_binary, "-l", str(stage / Path(*PurePosixPath(relative).parts))], check=False)
        if completed.returncode != 0:
            raise RuntimeError(f"PHP lint failed for {relative}: {completed.stderr.strip()}")


def prepare_component(repo: Path, site: Path, state_root: Path, component: dict[str, Any],
                      change_base_sha: str, target_sha: str) -> dict[str, Any]:
    component_id = component["id"]
    component_state = load_component_state(state_root, component_id)
    state_base_sha = component_state["base_sha"]
    changes = changed_paths(repo, change_base_sha, target_sha, component["source"], component["excluded"])
    result: dict[str, Any] = {
        "component": component_id,
        "base_sha": change_base_sha,
        "state_base_sha": state_base_sha,
        "target_sha": target_sha,
        "files": sorted(changes),
        "status": "unchanged",
        "risk": "none",
        "reasons": [],
        "state": component_state,
    }
    if not changes:
        return result

    risk, reasons = classify(component, changes)
    result["risk"] = risk
    result["reasons"] = reasons
    if risk == "critical":
        result["status"] = "manual_required"
        return result

    production_target = site / component["target"]
    current, symlinks = scan_directory(production_target, component["excluded"])
    if symlinks:
        result["status"] = "conflict"
        result["reasons"].append(f"production symlinks detected: {symlinks[:5]}")
        return result

    expected: dict[str, str] = component_state.get("expected", {})
    protected = set(component_state.get("protected", []))
    for relative in set(current) | set(expected):
        if current.get(relative) != expected.get(relative):
            protected.add(relative)

    target_tree = git_tree(repo, target_sha, component["source"], component["excluded"])
    conflicts: list[str] = []
    already_applied: list[str] = []
    for relative in changes:
        actual_hash = current.get(relative)
        expected_hash = expected.get(relative)
        target_hash = target_tree.get(relative, {}).get("sha256")
        if actual_hash == target_hash:
            already_applied.append(relative)
            continue
        if relative in protected or actual_hash != expected_hash:
            conflicts.append(relative)
    if conflicts:
        component_state["protected"] = sorted(protected)
        result["status"] = "conflict"
        result["reasons"].append(f"protected production drift: {conflicts}")
        return result

    if len(already_applied) == len(changes):
        result.update({
            "status": "already_deployed",
            "changes": changes,
            "target": production_target,
            "target_tree": target_tree,
            "already_applied": already_applied,
            "protected": protected,
        })
        return result

    parent = production_target.parent
    if not parent.is_dir():
        raise RuntimeError(f"Missing production parent: {parent}")
    stage = Path(tempfile.mkdtemp(prefix=f".{production_target.name}.deploy-", dir=parent))
    if production_target.exists():
        shutil.copytree(production_target, stage, dirs_exist_ok=True, symlinks=False)
    try:
        for relative, status in changes.items():
            destination = stage / Path(*safe_relative(relative).parts)
            if status == "D":
                if destination.is_file():
                    destination.unlink()
                continue
            entry = target_tree.get(relative)
            if not entry:
                raise RuntimeError(f"Missing target Git blob: {component['source']}/{relative}")
            write_blob(repo, target_sha, f"{component['source']}/{relative}", destination, entry["mode"])
        validate_stage(stage, changes, os.environ.get("ASODELTOL_PHP", "/usr/bin/php8.4"))
    except Exception:
        shutil.rmtree(stage)
        raise

    result.update({
        "status": "prepared",
        "stage": stage,
        "target": production_target,
        "changes": changes,
        "target_tree": target_tree,
        "already_applied": already_applied,
        "protected": protected,
    })
    return result


def swap_component(item: dict[str, Any], state_root: Path, timestamp: str) -> dict[str, Any]:
    target: Path = item["target"]
    stage: Path = item["stage"]
    backup = state_root / "backups" / item["component"] / f"{timestamp}-{item['base_sha'][:12]}"
    backup.parent.mkdir(parents=True, exist_ok=True)
    if backup.exists():
        raise RuntimeError(f"Backup destination already exists: {backup}")
    if target.exists():
        os.replace(target, backup)
    try:
        os.replace(stage, target)
    except Exception:
        if backup.exists() and not target.exists():
            os.replace(backup, target)
        raise
    item["backup"] = backup
    item["status"] = "deployed"
    return item


def rollback(swapped: list[dict[str, Any]], state_root: Path, timestamp: str) -> None:
    for item in reversed(swapped):
        target: Path = item["target"]
        backup: Path = item["backup"]
        failed = state_root / "failed" / item["component"] / f"{timestamp}-{item['target_sha'][:12]}"
        failed.parent.mkdir(parents=True, exist_ok=True)
        if target.exists():
            os.replace(target, failed)
        if backup.exists():
            os.replace(backup, target)
        item["status"] = "rolled_back"


def purge_and_health(site: Path) -> None:
    php = os.environ.get("ASODELTOL_PHP", "/usr/bin/php8.4")
    purge = site / "admin/cli/purge_caches.php"
    if not purge.is_file():
        raise RuntimeError(f"Moodle cache purge script is missing: {purge}")
    completed = run([php, str(purge)], check=False)
    if completed.returncode != 0:
        raise RuntimeError(f"Moodle cache purge failed: {completed.stderr.strip()}")

    url = os.environ.get("ASODELTOL_PUBLIC_URL", "https://aulaasodeltol.online/").rstrip("/")
    health = run([
        "curl", "--fail", "--silent", "--show-error", "--location",
        "--max-time", "15", "--output", "/dev/null", "--write-out", "%{http_code}",
        f"{url}/login/index.php",
    ], check=False)
    if health.returncode != 0 or health.stdout.strip() != "200":
        raise RuntimeError(f"Public Moodle health check failed: HTTP {health.stdout.strip() or 'unavailable'}")


def commit_state(item: dict[str, Any], state_root: Path) -> None:
    component_state = item["state"]
    component_state["base_sha"] = item["target_sha"]
    component_state["last_status"] = item["status"]
    component_state["last_processed_at"] = utc_now()
    if item["status"] in {"deployed", "already_deployed"}:
        current, _ = scan_directory(item["target"], [])
        expected: dict[str, str] = component_state.get("expected", {})
        protected = set(item["protected"])
        for relative in item["changes"]:
            if relative in current:
                expected[relative] = current[relative]
            else:
                expected.pop(relative, None)
            protected.discard(relative)
        component_state["expected"] = expected
        component_state["protected"] = sorted(protected)
        component_state["deployed_at"] = utc_now()
    atomic_json(state_path(state_root, item["component"]), component_state)


def deploy(repo: Path, site: Path, state_root: Path, policy: dict[str, Any],
           requested_before: str, requested_sha: str) -> dict[str, Any]:
    if not SHA_RE.fullmatch(requested_sha) or not SHA_RE.fullmatch(requested_before):
        raise RuntimeError("Invalid requested commit SHA")
    started = time.monotonic()
    started_at = utc_now()
    git(repo, "fetch", "--quiet", "--prune", "origin", "+refs/heads/main:refs/remotes/origin/main")
    current_main = str(git(repo, "rev-parse", "refs/remotes/origin/main")).strip()
    target_sha = requested_sha
    ancestor = run(["git", "merge-base", "--is-ancestor", target_sha, current_main], cwd=repo, check=False)
    if ancestor.returncode != 0:
        raise RuntimeError("Webhook commit is not an ancestor of current origin/main")

    if requested_before == ZERO_SHA:
        requested_before = str(git(repo, "rev-parse", f"{target_sha}^1")).strip()
    change_ancestor = run(
        ["git", "merge-base", "--is-ancestor", requested_before, target_sha],
        cwd=repo,
        check=False,
    )
    if change_ancestor.returncode != 0:
        raise RuntimeError("Webhook before commit is not an ancestor of its target commit")

    prepared: list[dict[str, Any]] = []
    results: list[dict[str, Any]] = []
    for component in policy["components"]:
        item = prepare_component(repo, site, state_root, component, requested_before, target_sha)
        results.append(item)
        if item["status"] == "prepared":
            prepared.append(item)

    timestamp = dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    swapped: list[dict[str, Any]] = []
    try:
        for item in prepared:
            swapped.append(swap_component(item, state_root, timestamp))
        if swapped:
            purge_and_health(site)
            for item in swapped:
                target_tree = item["target_tree"]
                current, _ = scan_directory(item["target"], [])
                for relative, status in item["changes"].items():
                    expected_hash = None if status == "D" else target_tree[relative]["sha256"]
                    if current.get(relative) != expected_hash:
                        raise RuntimeError(f"Post-deploy hash mismatch: {item['component']}/{relative}")
    except Exception:
        if swapped:
            rollback(swapped, state_root, timestamp)
            try:
                purge_and_health(site)
            except Exception:
                pass
        for item in prepared:
            stage = item.get("stage")
            if isinstance(stage, Path) and stage.exists():
                shutil.rmtree(stage)
        raise

    for item in results:
        commit_state(item, state_root)

    public_results = [{key: value for key, value in item.items()
                       if key in {"component", "base_sha", "target_sha", "files", "status", "risk", "reasons"}}
                      for item in results]
    status = "deployed" if swapped else "no_changes"
    if any(item["status"] in {"manual_required", "conflict"} for item in results):
        status = "partial" if swapped else "manual_required"
    record = {
        "before_sha": requested_before,
        "requested_sha": requested_sha,
        "deployed_sha": target_sha,
        "status": status,
        "started_at": started_at,
        "completed_at": utc_now(),
        "duration_seconds": round(time.monotonic() - started, 3),
        "components": public_results,
    }
    atomic_json(state_root / "releases" / f"{timestamp}-{target_sha[:12]}.json", record)
    atomic_json(state_root / "last-result.json", record)
    return record


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--initialize", action="store_true")
    parser.add_argument("--before")
    parser.add_argument("--sha")
    args = parser.parse_args()

    repo = Path(os.environ["ASODELTOL_REPO_DIR"]).resolve()
    site = Path(os.environ["ASODELTOL_SITE_ROOT"]).resolve()
    state_root = Path(os.environ["ASODELTOL_STATE_DIR"]).resolve()
    policy_path = Path(os.environ.get("ASODELTOL_POLICY", "/opt/asodeltol-deploy/deploy-policy.json"))
    policy = json.loads(policy_path.read_text(encoding="utf-8"))
    state_root.mkdir(parents=True, exist_ok=True)

    lock_path = state_root / "deploy.lock"
    with lock_path.open("a+", encoding="utf-8") as lock:
        fcntl.flock(lock.fileno(), fcntl.LOCK_EX)
        if args.initialize:
            commit = str(git(repo, "rev-parse", "refs/remotes/origin/main")).strip()
            result = initialise(repo, site, state_root, policy, commit)
        elif args.sha and args.before:
            result = deploy(repo, site, state_root, policy, args.before.lower(), args.sha.lower())
        else:
            parser.error("--initialize or both --before and --sha are required")
    print(json.dumps(result, ensure_ascii=False, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(json.dumps({"status": "failed", "error": str(error)}, ensure_ascii=False), file=sys.stderr)
        raise SystemExit(1)
