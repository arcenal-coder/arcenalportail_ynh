"""Offline S1-05 preparation; native execution deliberately not implemented."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import sys
import tomllib
import zipfile

CANDIDATE_SHA = "7254e687a06119ecc419017eba333b350a513e7f4dfbc69ea48da3f2dcacffdf"
HISTORICAL_SHA = "c9e3720398c5a92bb56ff7c21b4c44dee3c772ad97a5478a59d15e38116c4502"
INVENTORY_SHA = "a6aa35e766265d2b13e9bc0f229f828762662aa751c38f5725d293a19ec7be6b"
BASELINE_COMMIT = "4cae33026b570c089a73edaba505f1067117cc42"
REQUIRED = {("TEST_PACKAGE_LINTER", ""), ("TEST_INSTALL", "root"), ("TEST_INSTALL", "subdir"),
            ("TEST_INSTALL", "multi"), ("TEST_BACKUP_RESTORE", ""), ("TEST_UPGRADE", ""),
            ("TEST_CHANGE_URL", "")} | {("TEST_UPGRADE", commit) for commit in (
                "0740d8a789772d6b061776945d69af33b1159d9b", "3ba0494cdd1191b262593601814f54141e67ad30",
                "39eaa3c66676a53ebbf99bab497a879e5d556f73", "cc0af029bb944761600bd960ae73eca2b6060ea6",
                "6af4b824abab2f52862406d4fa5c2155b439386a")}
S1_CHECKS = ("backup_restore_4", "upgrade_4_to_6", "backup_restore_6", "migration_failure_atomicity",
             "coherent_recovery_4", "profiles_documents_groups_mail")


def assess(claim):
    """Check declarations only. Never authenticate native evidence or issue PASS.

    The unconditional blockers cannot be disabled by an input flag. A future
    raw-evidence adapter requires separate implementation, tests and review.
    """
    blockers = ["NATIVE_ADAPTER_NOT_IMPLEMENTED", "RAW_EVIDENCE_BINDING_NOT_IMPLEMENTED",
                "HISTORICAL_EQUIVALENCE_NOT_VERIFIED"]
    claim = claim if isinstance(claim, dict) else {}
    if claim.get("schema") != "vigilance.s1-05-claims/v1":
        blockers.append("SCHEMA")
    if claim.get("origin") != "native":
        blockers.append("ORIGIN")
    if claim.get("candidate_sha256") != CANDIDATE_SHA:
        blockers.append("CANDIDATE")
    baseline = claim.get("baseline")
    if not isinstance(baseline, dict) or baseline.get("equivalence") != "PROVEN" or baseline.get("historical_zip_sha256") != HISTORICAL_SHA:
        blockers.append("BASELINE")
    platform = claim.get("platform")
    if (not isinstance(platform, dict) or platform.get("yunohost_version") != "12.1.41.2"
            or platform.get("architecture") != "amd64"
            or not isinstance(platform.get("image_fingerprint"), str)
            or re.fullmatch(r"[0-9a-f]{64}", platform["image_fingerprint"]) is None):
        blockers.append("PLATFORM")
    tests = claim.get("native_tests")
    coverage = set()
    results_valid = isinstance(tests, list) and bool(tests)
    for test in tests if isinstance(tests, list) else []:
        if (not isinstance(test, dict) or not isinstance(test.get("test_type"), str)
                or not isinstance(test.get("test_arg"), str) or test.get("test_serie") != "default"):
            results_valid = False
            continue
        key = (test["test_type"], test["test_arg"])
        if key in coverage or test.get("main_result") != "success":
            results_valid = False
        coverage.add(key)
    if not REQUIRED <= coverage:
        blockers.append("NATIVE_COVERAGE")
    if not results_valid:
        blockers.append("NATIVE_RESULTS")
    checks = claim.get("s1_checks")
    if not isinstance(checks, dict) or any(checks.get(name) != "PASS" for name in S1_CHECKS):
        blockers.append("S1_CHECKS")
    return {"schema": "vigilance.s1-05-preparation/v1", "verdict": "BLOCKED", "s1_05_complete": False,
            "native_status": "NOT_EXECUTED", "historical_equivalence": "INDETERMINATE", "blockers": blockers}


def inspect_candidate(path):
    """Read the exact frozen archive, never extract/repack or execute its code."""
    path = Path(path)
    with path.open("rb") as stream:
        digest = hashlib.file_digest(stream, "sha256").hexdigest()
    if digest != CANDIDATE_SHA:
        raise ValueError("Candidate exacte ynh3 requise ; aucun remplacement accepté.")
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        if len(names) != 90 or len(set(names)) != len(names) or archive.testzip() is not None:
            raise ValueError("Inventaire/CRC de la candidate invalide.")
        manifest = tomllib.loads(archive.read("manifest.toml").decode())
        if manifest.get("id") != "arcenalportail" or manifest.get("version") != "1.1.0-beta7~ynh3":
            raise ValueError("Identité candidate différente.")
        migrations = sorted(name for name in names if name.startswith("sources/portal/migrations/"))
        if len(migrations) != 6 or [Path(name).name[:3] for name in migrations] != [f"{number:03}" for number in range(1, 7)]:
            raise ValueError("Migrations 001–006 requises.")
    return {"sha256": digest, "entry_count": len(names), "version": manifest["version"],
            "migrations": migrations, "status": "EXACT_LOCAL_ARCHIVE_CHECKED"}


def inspect_baseline(inventory):
    """Pin the existing content inventory, not an absent historical archive."""
    if not isinstance(inventory, dict) or not isinstance(inventory.get("entries"), list):
        raise ValueError("Inventaire baseline invalide.")
    entries = inventory["entries"]
    digest = hashlib.sha256(json.dumps(entries, sort_keys=True, separators=(",", ":")).encode()).hexdigest()
    if (digest != INVENTORY_SHA or inventory.get("canonical_entries_sha256") != INVENTORY_SHA
            or inventory.get("git_ref") != BASELINE_COMMIT or inventory.get("file_count") != 84
            or len(entries) != 84 or inventory.get("historical_zip_sha256") != HISTORICAL_SHA):
        raise ValueError("Inventaire différent de la baseline Git/schema4 examinée.")
    migrations = [entry for entry in entries if entry["path"].startswith("sources/portal/migrations/")]
    if inventory.get("migrations") != migrations:
        raise ValueError("Résumé migrations divergent de l’inventaire.")
    return {"git_ref": BASELINE_COMMIT, "inventory_sha256": digest, "file_count": len(entries),
            "status": "PINNED_INVENTORY_ONLY", "historical_equivalence": "INDETERMINATE",
            "baseline_archive_checked": False}


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError("Clé JSON dupliquée : " + key)
        result[key] = value
    return result


def read_json(stream):
    text = stream.read(2_000_001)
    if len(text) > 2_000_000:
        raise ValueError("JSON trop volumineux.")
    return json.loads(text, object_pairs_hook=unique_object)


def main():
    root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    preflight = commands.add_parser("preflight", help="Local read-only input checks, never native qualification")
    preflight.add_argument("--candidate", type=Path, default=root / "arcenal-qsse/dist/arcenalportail_ynh-1.1.0-beta.7-ynh3.zip")
    preflight.add_argument("--baseline-inventory", type=Path, default=root / ".arcenal/recette-sirh1/proposition-ci-2026-10-28/baseline-4cae330-files.json")
    claims = commands.add_parser("assess", help="Reject incomplete/unverified claims; always exits 2 in this slice")
    claims.add_argument("claims", help="JSON file, or - for stdin")
    args = parser.parse_args()
    try:
        if args.command == "preflight":
            candidate = inspect_candidate(args.candidate)
            with args.baseline_inventory.open() as stream:
                baseline = inspect_baseline(read_json(stream))
            result = {"schema": "vigilance.s1-05-preparation/v1", "verdict": "BLOCKED",
                      "s1_05_complete": False, "native_status": "NOT_EXECUTED",
                      "historical_equivalence": "INDETERMINATE", "local_input_checks": "PASS",
                      "blockers": ["NATIVE_ADAPTER_NOT_IMPLEMENTED", "RAW_EVIDENCE_BINDING_NOT_IMPLEMENTED",
                                   "HISTORICAL_EQUIVALENCE_NOT_VERIFIED", "PLATFORM_NOT_OBSERVED"],
                      "candidate": candidate, "baseline_inventory": baseline}
            exit_code = 0  # Success of input checks ONLY; S1-05 stays blocked.
        else:
            if args.claims == "-":
                claim = read_json(sys.stdin)
            else:
                with Path(args.claims).open() as stream:
                    claim = read_json(stream)
            result = assess(claim)
            exit_code = 2
    except (OSError, ValueError, KeyError, zipfile.BadZipFile, RecursionError) as error:
        result = {"verdict": "BLOCKED", "s1_05_complete": False, "input_error": str(error)}
        exit_code = 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return exit_code


if __name__ == "__main__":
    sys.exit(main())
