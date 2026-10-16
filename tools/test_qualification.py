import tempfile
import unittest
from pathlib import Path

from verify_package_report import qualify, UPGRADES
from prepare_package_check import prepare


class QualificationTest(unittest.TestCase):
    def setUp(self):
        self.commit = "a" * 40
        tests = [{"test_type": kind, "main_result": "success", "test_arg": None}
                 for kind in ["TEST_PACKAGE_LINTER", "TEST_INSTALL", "TEST_BACKUP_RESTORE", "TEST_UPGRADE", "TEST_CHANGE_URL"]]
        tests += [{"test_type": "TEST_UPGRADE", "test_arg": commit, "main_result": "success"} for commit in UPGRADES]
        self.reports = [{"app": "arcenalportail", "commit": self.commit, "app_version": "1.1.0~beta3+ynh1", "architecture": "amd64",
                         "yunohost_version": "12.1", "level": 0, "level_results": {str(n): True for n in (2,3,4,5)}, "tests": tests}]
        self.linter = {"error": {"AppCatalog.state_is_working": "not in community catalog"},
                       "critical": {"AppCatalog.is_in_catalog": "private catalog"}, "warning": []}

    def test_real_lifecycle_is_required_and_original_grade_preserved(self):
        result = qualify(self.reports, self.linter, self.commit)
        self.assertEqual(result["arcenal_technical_level"], 5)
        self.assertEqual(result["evidence"][0]["level"], 0)
        self.assertEqual(result["qualified_commit"], self.commit)

    def test_missing_reports_rejected(self):
        with self.assertRaises(ValueError): qualify([], self.linter, self.commit)

    def test_failed_scenario_rejected(self):
        self.reports[0]["tests"][1]["main_result"] = "failure"
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_skipped_or_missing_scenario_rejected(self):
        self.reports[0]["tests"] = self.reports[0]["tests"][1:]
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_missing_previous_upgrade_rejected(self):
        self.reports[0]["tests"].pop()
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_wrong_revision_rejected(self):
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, "b" * 40)

    def test_native_security_or_restore_failure_cannot_be_relabelled(self):
        self.reports[0]["level_results"]["5"] = False
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_missing_witness_or_unsafe_permissions_rejected(self):
        self.reports[0]["tests"][1]["test_notes"] = ["<style=danger>Missing witness file</style>"]
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_application_linter_error_rejected(self):
        self.linter["error"]["Scripts.syntax_error"] = "actual failure"
        with self.assertRaises(ValueError): qualify(self.reports, self.linter, self.commit)

    def test_incomplete_linter_rejected(self):
        with self.assertRaises(ValueError): qualify(self.reports, {}, self.commit)

    def test_https_preflight_preserves_native_test_lines(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/"lib/common.sh"
            path.parent.mkdir()
            original = "    ping -q -c 2 yunohost.org > /dev/null 2>&1 \\\n    || return 1\nNATIVE_TESTS\n"
            path.write_text(original)
            prepare(folder)
            self.assertIn("NATIVE_TESTS", path.read_text())
            self.assertIn("|| ping", path.read_text())
            self.assertIn("curl --fail", path.read_text())

    def test_unrecognized_preflight_refused(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/"lib/common.sh"
            path.parent.mkdir()
            path.write_text("different upstream")
            with self.assertRaises(ValueError): prepare(folder)
            self.assertEqual(path.read_text(), "different upstream")


if __name__ == "__main__": unittest.main()
