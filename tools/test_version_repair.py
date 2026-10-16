import tempfile
import unittest
from pathlib import Path
from repair_installed_version import repair


class VersionRepairTest(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        self.path = Path(self.folder.name)/"manifest.toml"
        self.original = 'id = "arcenalportail"\nversion = "1.1.0~beta1+ynh1"\nname = "VIGIlance"\n[resources]\n'
        self.path.write_text(self.original)

    def test_default_dry_run_does_not_change_any_bytes(self):
        self.assertTrue(repair(self.path)["change"])
        self.assertEqual(self.path.read_text(), self.original)

    def test_only_version_changes_and_original_is_saved(self):
        self.path.chmod(0o640)
        result = repair(self.path, True)
        self.assertEqual(self.path.read_text(), self.original.replace('1.1.0~beta1+ynh1', '1.1.0-beta1~ynh1'))
        self.assertEqual(self.path.with_name('manifest.toml.vigilance-version-backup').read_text(), self.original)
        self.assertEqual(self.path.stat().st_mode & 0o777, 0o640)
        self.assertTrue(result['applied'])
        self.assertFalse(repair(self.path, True)['change'])

    def test_beta2_is_repaired(self):
        self.path.write_text(self.original.replace('beta1', 'beta2'))
        self.assertEqual(repair(self.path, True)['new_version'], '1.1.0-beta2~ynh1')

    def test_other_app_is_never_modified(self):
        self.path.write_text(self.original.replace('arcenalportail', 'another_app'))
        with self.assertRaises(ValueError): repair(self.path, True)

    def test_valid_unknown_version_is_not_rewritten(self):
        self.path.write_text(self.original.replace('1.1.0~beta1+ynh1', '1.0.3~ynh1'))
        before = self.path.read_text()
        self.assertFalse(repair(self.path, True)['change'])
        self.assertEqual(before, self.path.read_text())

    def test_conflicting_backup_refuses_change(self):
        self.path.with_name('manifest.toml.vigilance-version-backup').write_text('other backup')
        with self.assertRaises(ValueError): repair(self.path, True)
        self.assertEqual(self.path.read_text(), self.original)

    def test_symlink_is_refused(self):
        link = self.path.parent/'link.toml'
        link.symlink_to(self.path)
        with self.assertRaises(ValueError): repair(link, True)

    def test_dangling_backup_symlink_is_refused(self):
        backup=self.path.with_name('manifest.toml.vigilance-version-backup')
        target=self.path.parent/'unrelated'
        backup.symlink_to(target)
        with self.assertRaises(ValueError): repair(self.path, True)
        self.assertFalse(target.exists())
        self.assertEqual(self.path.read_text(), self.original)

    def test_reused_backup_permissions_are_private(self):
        backup=self.path.with_name('manifest.toml.vigilance-version-backup')
        backup.write_text(self.original);backup.chmod(0o644)
        repair(self.path, True)
        self.assertEqual(backup.stat().st_mode & 0o777, 0o600)


if __name__ == '__main__': unittest.main()
