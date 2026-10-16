"""Private-catalog qualification from complete native evidence; originals unchanged."""
import argparse
import ast
import copy
import hashlib
import json
from pathlib import Path

UPGRADES = {"0740d8a789772d6b061776945d69af33b1159d9b", "3ba0494cdd1191b262593601814f54141e67ad30"}
REQUIRED = {("TEST_PACKAGE_LINTER", ""), ("TEST_INSTALL", "root"), ("TEST_INSTALL", "subdir"),
            ("TEST_INSTALL", "multi"), ("TEST_BACKUP_RESTORE", ""), ("TEST_UPGRADE", ""),
            ("TEST_CHANGE_URL", "")} | {("TEST_UPGRADE", commit) for commit in UPGRADES}
FLAGS = ("witness", "alias_traversal", "install_dir_permissions", "file_manually_modified", "too_many_warnings")


def catalog_findings(linter):
    if not {"error", "critical"} <= linter.keys():
        raise ValueError("Rapport linter incomplet.")
    if any(not isinstance(linter[key], (list, dict)) for key in ("error", "critical")):
        raise ValueError("Rapport linter invalide.")
    critical, errors = set(linter["critical"]), set(linter["error"])
    if critical - {"AppCatalog.is_in_catalog"}:
        raise ValueError("Défaut critique du paquet.")
    # Only the absent community entry explains this derived 'notworking' finding.
    allowed_error = {"AppCatalog.state_is_working"} if "AppCatalog.is_in_catalog" in critical else set()
    if errors - allowed_error:
        raise ValueError("Erreur applicative ou état communautaire réellement défectueux.")
    return {"critical": sorted(critical), "error": sorted(errors)}


def native_criteria(engine, tests):
    """Evaluate the pinned checker's unmodified pure level-1..5 functions.

    Decorators merely register the functions in the CLI report. Removing these
    decorators avoids importing/running the upstream CLI or its renderers.
    """
    try:
        tree = ast.parse(engine)
    except SyntaxError as error:
        raise ValueError("Critères du checker natif invalides.") from error
    functions = [node for node in tree.body if isinstance(node, ast.FunctionDef)
                 and node.name in {f"level_{level}" for level in range(1, 6)}]
    if len(functions) != 5:
        raise ValueError("Critères du checker natif non reconnus.")
    for node in functions:
        node.decorator_list = []
    module = ast.fix_missing_locations(ast.Module(body=functions, type_ignores=[]))
    namespace = {}
    exec(compile(module, "pinned-native-levels", "exec"), namespace)
    return {str(level): bool(namespace[f"level_{level}"](tests)) for level in range(1, 6)}


def qualify(reports, linter, expected_commit, native_tests, engine):
    if len(reports) != 1:
        raise ValueError("Un rapport complet pour cette plateforme est requis.")
    report = reports[0]
    if report.get("commit") != expected_commit or report.get("app") != "arcenalportail":
        raise ValueError("Révision ou application différente de la candidate.")
    if not all(report.get(key) for key in ("app_version", "architecture", "yunohost_version")):
        raise ValueError("Identité de la qualification incomplète.")
    tests = report.get("tests", [])
    coverage = {(test.get("test_type"), test.get("test_arg") or "") for test in tests if test.get("test_serie") == "default"}
    if not REQUIRED <= coverage:
        raise ValueError("Couverture root/subdir/multi, restauration ou upgrades incomplète.")
    if any(test.get("main_result") != "success" for test in tests):
        raise ValueError("Un scénario natif a échoué.")
    raw_coverage = {(test.get("test_type"), test.get("test_arg") or "") for test in native_tests if test.get("test_serie") == "default"}
    if not REQUIRED <= raw_coverage or len(native_tests) != len(tests):
        raise ValueError("Preuves détaillées natives manquantes.")
    adjusted = copy.deepcopy(native_tests)
    for test in adjusted:
        result = test["results"]
        if result.get("main_result") != "success" or any(result.get(flag) for flag in FLAGS):
            raise ValueError("Échec natif, perte de données, permissions ou sécurité non conforme.")
        if test["test_type"] == "TEST_PACKAGE_LINTER":
            removed = catalog_findings(result)
            for severity in ("error", "critical"):
                result[severity] = [item for item in result[severity] if item not in removed[severity]]
    findings = catalog_findings(linter)
    criteria = native_criteria(engine, adjusted)
    if not all(criteria.values()):
        raise ValueError("Les critères natifs jusqu'au niveau 5 ne sont pas satisfaits.")
    return {
        "schema": "arcenal-technical-qualification/v1", "arcenal_technical_level": 5,
        "scope": "Catalogue ARCenal uniquement ; aucune note communautaire officielle attribuée.",
        "qualified_commit": expected_commit, "upgrade_from": sorted(UPGRADES),
        "checker_revision": "f7d32cadac3cfced282decede024707fba9e6ec6",
        "native_engine_sha256": hashlib.sha256(engine.encode()).hexdigest(),
        "private_catalog_criteria": criteria, "official_catalog_only_findings": findings,
        "linter_warnings": linter.get("warning", []), "evidence": reports,
    }


def read_native_tests(directory, expected_commit):
    contexts = [path.parent for path in directory.glob("*/commit") if path.read_text().strip() == expected_commit]
    if len(contexts) != 1:
        raise ValueError("Contexte natif détaillé introuvable ou ambigu.")
    context = contexts[0]
    tests = []
    for path in sorted((context / "tests").glob("*.json")):
        test = json.loads(path.read_text())
        test["results"] = json.loads((context / "results" / path.name).read_text())
        tests.append(test)
    return tests


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    parser.add_argument("--linter-report", type=Path, required=True)
    parser.add_argument("--expected-commit", required=True)
    parser.add_argument("--qualification-output", type=Path, required=True)
    args = parser.parse_args()
    try:
        reports = [json.loads(path.read_text()) for path in sorted(args.directory.glob("results_*.json"))]
        native = read_native_tests(args.directory / "arcenal-evidence", args.expected_commit)
        engine = (args.directory / "lib/analyze_test_results.py").read_text()
        result = qualify(reports, json.loads(args.linter_report.read_text()), args.expected_commit, native, engine)
        args.qualification_output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n")
    except (ValueError, OSError, KeyError) as error:
        raise SystemExit(str(error)) from error
    print("Cycle complet et critères natifs 1–5 prouvés, hors seuls effets de l'absence du catalogue communautaire.")
    print("Qualification technique ARCenal : 5 ; rapports communautaires originaux conservés.")


if __name__ == "__main__":
    main()
