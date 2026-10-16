"""Qualify a private ARCenal catalog from real, immutable native lifecycle evidence.

Never modifies the original community result. Only catalog-membership findings
are distinguished from actual application/packaging failures.
"""
import argparse
import json
from pathlib import Path

UPGRADES = {
    "0740d8a789772d6b061776945d69af33b1159d9b",
    "3ba0494cdd1191b262593601814f54141e67ad30",
}


def qualify(reports, linter, expected_commit):
    if not reports:
        raise ValueError("Aucun rapport package_check produit.")
    required = {"TEST_PACKAGE_LINTER", "TEST_INSTALL", "TEST_BACKUP_RESTORE", "TEST_UPGRADE", "TEST_CHANGE_URL"}
    for report in reports:
        tests = report.get("tests", [])
        if not required <= {test.get("test_type") for test in tests}:
            raise ValueError("Scénarios de qualification manquants.")
        if any(test.get("main_result") != "success" for test in tests):
            raise ValueError("Un scénario natif est en échec ou non qualifié.")
        if any("<style=danger>" in str(note) for test in tests for note in test.get("test_notes", [])):
            raise ValueError("Un contrôle transversal natif signale une perte de données ou un défaut de sécurité.")
        levels = report.get("level_results", {})
        if any(levels.get(str(level)) is not True for level in (2, 3, 4, 5)):
            raise ValueError("Les critères natifs installation, upgrade, restauration et sécurité/linter ne sont pas tous satisfaits.")
        proved_upgrades = {test.get("test_arg") for test in tests if test.get("test_type") == "TEST_UPGRADE"}
        if not UPGRADES <= proved_upgrades:
            raise ValueError("Mises à jour depuis beta1 et beta2 non prouvées.")
        if report.get("commit") != expected_commit:
            raise ValueError("Le rapport ne porte pas sur la révision demandée.")
        if report.get("app") != "arcenalportail":
            raise ValueError("Le rapport ne porte pas sur VIGIlance.")
        if not all(report.get(key) for key in ("app_version", "architecture", "yunohost_version")):
            raise ValueError("Identité de la version qualifiée incomplète.")
    permitted = {"error": {"AppCatalog.state_is_working"}, "critical": {"AppCatalog.is_in_catalog"}}
    if not set(permitted) <= linter.keys():
        raise ValueError("Rapport linter incomplet.")
    for severity, allowed in permitted.items():
        if not isinstance(linter[severity], (list, dict)) or set(linter[severity]) - allowed:
            raise ValueError("Défaut applicatif ou de packaging signalé par le linter.")
    return {
        "schema": "arcenal-technical-qualification/v1",
        "arcenal_technical_level": 5,
        "scope": "Catalogue ARCenal uniquement ; aucune note communautaire officielle attribuée.",
        "checker_revision": "f7d32cadac3cfced282decede024707fba9e6ec6",
        "qualified_commit": expected_commit,
        "upgrade_from": sorted(UPGRADES),
        "official_catalog_only_findings": {key: linter[key] for key in permitted},
        "linter_warnings": linter.get("warning", []),
        "evidence": [{key: report[key] for key in ("commit", "app_version", "architecture", "yunohost_version", "level", "tests")} for report in reports],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    parser.add_argument("--linter-report", type=Path, required=True)
    parser.add_argument("--expected-commit", required=True)
    parser.add_argument("--qualification-output", type=Path, required=True)
    args = parser.parse_args()
    reports = [json.loads(path.read_text()) for path in sorted(args.directory.glob("results_*.json"))]
    try:
        result = qualify(reports, json.loads(args.linter_report.read_text()), args.expected_commit)
    except (ValueError, OSError, KeyError) as error:
        raise SystemExit(str(error)) from error
    args.qualification_output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n")
    print("Cycle de vie et mises à jour beta1/beta2 prouvés pour la révision exacte.")
    print("Qualification technique ARCenal : niveau 5 ; résultat communautaire original conservé.")


if __name__ == "__main__":
    main()
