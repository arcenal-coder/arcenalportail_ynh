import copy
import tempfile
import unittest
import subprocess
from pathlib import Path
from verify_package_report import qualify, REQUIRED, LEGACY_UPGRADES
from prepare_package_check import prepare

# Pure fixture functions representing native criteria; real CI loads the pinned engine.
ENGINE = '''
def level_1(tests): return not any(t['results'].get('witness') for t in tests) and all(not t['results'].get('critical',[]) for t in tests)
def level_2(tests): return all(t['results']['main_result']=='success' for t in tests if t['test_type']=='TEST_INSTALL')
def level_3(tests): return all(t['results']['main_result']=='success' for t in tests if t['test_type']=='TEST_UPGRADE')
def level_4(tests): return all(t['results']['main_result']=='success' for t in tests if t['test_type']=='TEST_BACKUP_RESTORE')
def level_5(tests): return all(not t['results'].get('error',[]) and not t['results'].get('alias_traversal') for t in tests)
'''


class QualificationTest(unittest.TestCase):
    def setUp(self):
        self.commit = 'a'*40
        tests = [{'test_type':kind,'test_arg':arg,'test_serie':'default','main_result':'success'} for kind,arg in sorted(REQUIRED)]
        self.reports = [{'app':'arcenalportail','commit':self.commit,'app_version':'1.1.0-beta3~ynh1',
                         'architecture':'amd64','yunohost_version':'12.1','level':0,'tests':tests}]
        self.linter = {'error':{'AppCatalog.state_is_working':'absent community entry'},
                       'critical':{'AppCatalog.is_in_catalog':'private catalog'},'warning':[]}
        self.native = [dict(test,results={'main_result':'success','critical':[],'error':[]}) for test in tests]
        for test in self.native:
            if test['test_type']=='TEST_PACKAGE_LINTER':
                test['results']['critical']=list(self.linter['critical'])
                test['results']['error']=list(self.linter['error'])

    def run_qualification(self):
        return qualify(self.reports,self.linter,self.commit,self.native,ENGINE)

    def test_absent_community_entry_is_distinct_from_native_failures(self):
        before=copy.deepcopy(self.native)
        result=self.run_qualification()
        self.assertEqual(result['arcenal_technical_level'],5)
        self.assertEqual(result['evidence'][0]['level'],0)
        self.assertEqual(before,self.native)

    def test_missing_reports_rejected(self):
        self.reports=[]
        with self.assertRaises(ValueError):self.run_qualification()

    def test_failed_scenario_rejected(self):
        self.native[1]['results']['main_result']='failure'
        with self.assertRaises(ValueError):self.run_qualification()

    def test_missing_install_variant_rejected(self):
        self.reports[0]['tests']=[t for t in self.reports[0]['tests'] if t['test_arg']!='multi']
        self.native=[t for t in self.native if t['test_arg']!='multi']
        with self.assertRaises(ValueError):self.run_qualification()

    def test_missing_current_upgrade_rejected(self):
        self.reports[0]['tests']=[t for t in self.reports[0]['tests'] if not(t['test_type']=='TEST_UPGRADE' and t['test_arg']=='')]
        with self.assertRaises(ValueError):self.run_qualification()

    def test_missing_historical_upgrade_rejected(self):
        self.reports[0]['tests'].pop()
        with self.assertRaises(ValueError):self.run_qualification()

    def test_missing_published_beta3_upgrade_rejected(self):
        beta3='39eaa3c66676a53ebbf99bab497a879e5d556f73'
        self.reports[0]['tests']=[test for test in self.reports[0]['tests'] if test['test_arg']!=beta3]
        self.native=[test for test in self.native if test['test_arg']!=beta3]
        with self.assertRaises(ValueError): self.run_qualification()

    def test_beta3_unsafe_permissions_cannot_be_excused_as_legacy(self):
        beta3=next(test for test in self.native if test['test_arg']=='39eaa3c66676a53ebbf99bab497a879e5d556f73')
        beta3['results']['install_dir_permissions']='failure'
        beta3['results']['arc_post_upgrade_install_mode']='750'
        with self.assertRaises(ValueError): self.run_qualification()

    def test_wrong_revision_rejected(self):
        self.commit='b'*40
        with self.assertRaises(ValueError):self.run_qualification()

    def test_real_community_broken_state_not_tolerated(self):
        self.linter['critical']={}
        with self.assertRaises(ValueError):self.run_qualification()

    def test_application_linter_error_rejected(self):
        self.linter['error']['Scripts.syntax_error']='actual failure'
        with self.assertRaises(ValueError):self.run_qualification()

    def test_native_security_or_restore_failure_cannot_be_relabelled(self):
        self.native[1]['results']['alias_traversal']=True
        with self.assertRaises(ValueError):self.run_qualification()

    def test_missing_witness_rejected(self):
        self.native[1]['results']['witness']=True
        with self.assertRaises(ValueError):self.run_qualification()

    def test_legacy_permissions_need_proven_post_upgrade_repair(self):
        historical=next(t for t in self.native if t['test_type']=='TEST_UPGRADE' and t['test_arg'] in LEGACY_UPGRADES)
        historical['results']['install_dir_permissions']='failure'
        with self.assertRaises(ValueError): self.run_qualification()
        historical['results']['arc_post_upgrade_install_mode']='750'
        self.assertEqual(self.run_qualification()['arcenal_technical_level'],5)
        self.assertEqual(historical['results']['install_dir_permissions'],'failure')

    def test_current_unsafe_permissions_can_never_be_excused(self):
        current=next(t for t in self.native if t['test_type']=='TEST_UPGRADE' and not t['test_arg'])
        current['results']['install_dir_permissions']='failure'
        current['results']['arc_post_upgrade_install_mode']='750'
        with self.assertRaises(ValueError): self.run_qualification()

    def test_unknown_grading_engine_rejected(self):
        with self.assertRaises(ValueError):qualify(self.reports,self.linter,self.commit,self.native,'different upstream')

    def test_preparation_preserves_tests_and_cleanup(self):
        with tempfile.TemporaryDirectory() as folder:
            common=Path(folder)/'lib/common.sh';common.parent.mkdir()
            common.write_text("    ping -q -c 2 yunohost.org > /dev/null 2>&1 \\\n    || return 1\nNATIVE_TESTS\n")
            script=Path(folder)/'package_check.sh'
            script.write_text('    [ -n "$TEST_CONTEXT" ] && rm -rf "$TEST_CONTEXT"\n')
            tests=common.parent/'tests.sh'
            tests.write_text('    ret=$?\n\n    metrics_stop\n\n    return $ret\n}\n\nTEST_PORT_ALREADY_USED()')
            prepare(folder)
            self.assertIn('NATIVE_TESTS',common.read_text())
            self.assertIn('|| ping',common.read_text())
            self.assertIn('cp -a',script.read_text())
            self.assertIn('rm -rf "$TEST_CONTEXT"',script.read_text())
            self.assertIn('arc_post_upgrade_install_mode',tests.read_text())
            # Incus emits a CR before the final newline. Test the exact rendered
            # shell guard without a YunoHost container or external jq dependency.
            guard=tests.read_text().split('\nTEST_PORT_ALREADY_USED()')[0]
            result_path=Path(folder)/'result.json';result_path.write_text('{}')
            shell='LXC_EXEC(){ printf "%s\\r" "$MODE"; }; jq(){ printf "{}"; }; metrics_stop(){ :; }; app_id=arcenalportail; current_test_results='+str(result_path)+'; check_mode(){\n'+guard+'\n'
            for mode,expected in [('750',0),('755',1),('750x',1)]:
                result=subprocess.run(['bash','-c',shell+'MODE='+mode+'; true; check_mode'])
                self.assertEqual(result.returncode,expected)


if __name__=='__main__':unittest.main()
