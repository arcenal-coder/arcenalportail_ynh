"""Fail closed on the limited diagnostic. Never writes a release qualification."""
import argparse
import json
from pathlib import Path
from verify_package_report import read_native_tests, MAIL_CRITERIA


def verify(tests):
    coverage={(test.get('test_type'),test.get('test_arg') or '') for test in tests}
    if coverage != {('TEST_INSTALL','root'),('TEST_UPGRADE','')}:
        raise ValueError('Diagnostic requires root setup and one upgrade')
    if any(test.get('test_serie')!='mail_diagnostic' or test['results'].get('main_result')!='success' for test in tests):
        raise ValueError('Native diagnostic setup or upgrade failed')
    upgrade=next(test for test in tests if test['test_type']=='TEST_UPGRADE')
    proof=upgrade['results'].get('arc_mail_transport',{})
    if proof.get('schema')!='vigilance-native-mail/v1' or any(proof.get(key) is not True for key in MAIL_CRITERIA):
        raise ValueError('Real local mail not proved')


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directory',type=Path)
    parser.add_argument('--expected-commit',required=True)
    args=parser.parse_args()
    verify(read_native_tests(args.directory/'arcenal-evidence',args.expected_commit))
    print('Diagnostic MTA local réussi ; aucune qualification complète ni niveau attribué.')
