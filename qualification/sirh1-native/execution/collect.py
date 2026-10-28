"""Assemble the raw native receipts, not a scoring or declaration-based verdict."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import tarfile
import zipfile

import guest
import prepare


def read_tar(path):
    files = {}
    with tarfile.open(path, 'r:gz') as archive:
        total = 0
        for member in archive.getmembers():
            if member.isdir() and member.name == 'evidence':
                continue
            parts = Path(member.name).parts
            if not member.isfile() or len(parts) != 2 or parts[0] != 'evidence' or parts[1] in files:
                raise ValueError('Unexpected guest evidence archive member')
            total += member.size
            if total > 32_000_000 or member.size > 4_000_000:
                raise ValueError('Guest evidence size bound exceeded')
            files[parts[1]] = archive.extractfile(member).read()
    return files


def check_files(files):
    completion = json.loads(files['completed.json'])
    # Presence alone is insufficient: bind every recorded state/output/event.
    manifest = completion['files']
    if set(manifest) != set(files)-{'completed.json'}:
        raise ValueError('Missing or additional raw evidence')
    for name, expected in manifest.items():
        if hashlib.sha256(files[name]).hexdigest() != expected:
            raise ValueError('Raw evidence hash mismatch: '+name)
    commands = []
    for name in sorted(files):
        if re.fullmatch(r'command-\d+\.json', name):
            command = json.loads(files[name])
            for stream in ('stdout', 'stderr'):
                raw = files[name.removesuffix('.json')+'.'+stream]
                if hashlib.sha256(raw).hexdigest() != command[stream+'_sha256']:
                    raise ValueError('Command output differs: '+name)
            expected_failure = command['label'] in ('faulty-native-upgrade', 'fault-confirm-migrate')
            code = command['returncode']
            if (expected_failure and (type(code) is not int or code in (0, 124))) or (not expected_failure and code != 0):
                raise ValueError('Unexpected native command exit: '+command['label'])
            if command['label'] == 'fault-confirm-migrate' and code != 1:
                raise ValueError('Fault confirmation must exit through the real migrate failure handler')
            if command['ended_utc'] < command['started_utc']:
                raise ValueError('Command timestamps reversed')
            commands.append(command)
    labels = [c['label'] for c in commands]
    required = ['probe-seed4', 'activate-real-groups', 'backup-s105-schema4', 'restore-s105-schema4',
                'probe-target6', 'backup-s105-schema6', 'restore-s105-schema6', 'faulty-native-upgrade',
                'fault-confirm-migrate', 'failure-maintenance', 'probe-verify6', 'timer-status', 'php-service', 'nginx-service', 'smtp-confinement']
    if not set(required) <= set(labels):
        raise ValueError('Native operation receipts missing')
    if labels.index('backup-s105-schema4') >= labels.index('faulty-native-upgrade'):
        raise ValueError('No schema4 checkpoint before fault')
    return commands


def collect(work, inputs):
    work, inputs = Path(work), Path(inputs)
    failures = sorted((work/'evidence').glob('preupgrade-failure-*.json'))
    if failures:
        primary = json.loads(failures[0].read_text())
        for archive in sorted((work/'evidence').glob('*.tar.gz')):
            try:
                files = read_tar(archive)
                if 'failure.json' in files:
                    primary['guest'] = json.loads(files['failure.json'])
            except (OSError, ValueError, KeyError, tarfile.TarError):
                primary['collection_error'] = 'Guest archive unreadable; primary failure retained'
        return {'technical_cycle': 'FAILED_OR_INCOMPLETE', 's1_05_complete': False,
                'historical_equivalence': 'INDETERMINATE', 'primary_failure': primary,
                'qualification_present': (work/'package-qualification.json').is_file()}
    work, inputs = Path(work), Path(inputs)
    receipt = prepare.verify_inputs(inputs)
    stage = json.loads((work/'stage.json').read_text())
    qualified = json.loads((work/'package-qualification.json').read_text())
    if qualified['qualified_commit'] != stage['checker_control_commit'] or stage['candidate_sha256'] != receipt['candidate_sha256']:
        raise ValueError('Control/import/frozen input identities differ')
    reports = qualified['evidence']
    if len(reports) != 1 or reports[0]['app_version'] != '1.1.0-beta7~ynh3' or reports[0]['yunohost_version'] != '12.1.41.2' or reports[0]['architecture'] != 'amd64':
        raise ValueError('Checker platform/candidate mismatch')
    coverage = {(t['test_type'], t.get('test_arg') or '') for t in reports[0]['tests'] if t.get('test_serie') == 'default'}
    if not (prepare.control.REQUIRED | {('TEST_UPGRADE', stage['baseline_import_commit'])}) <= coverage:
        raise ValueError('Historical or additional beta6 scenario absent')
    image = json.loads((work.parent/'s105-platform/image.json').read_text())
    image = image.get('metadata', image)
    if not isinstance(image.get('fingerprint'), str) or re.fullmatch('[a-f0-9]{64}', image['fingerprint']) is None:
        raise ValueError('Actual Incus image fingerprint missing')
    archives = list((work/'evidence').glob('*.tar.gz'))
    if len(archives) != 1:
        raise ValueError('Exactly one additional beta6 native lifecycle required')
    files = read_tar(archives[0])
    commands = check_files(files)
    states = {name: json.loads(files[name+'.json']) for name in (
        'before4', 'restored4', 'upgraded6', 'before6', 'restored6', 'failure-before4', 'failed-migration4', 'recovered4', 'final6')}
    for name in ('restored4', 'failure-before4', 'recovered4'):
        guest.compare_state(states['before4'], states[name])
    for name in ('restored6', 'final6'):
        guest.compare_state(states['before6'], states[name])
    before, upgraded, failed = states['before4'], states['upgraded6'], states['failed-migration4']
    baseline = json.loads((inputs/'expected-baseline-code.json').read_text())
    guest.check_installed(before, baseline)
    if len(before['schema']) != 4 or len(upgraded['schema']) != 6 or upgraded['schema'][:4] != before['schema']:
        raise ValueError('Raw migration states do not show 4→6')
    if failed['schema'] != before['schema'] or failed['tables'] != before['tables'] or 's105_fault_marker' in failed['tables']:
        raise ValueError('Failed migration did not preserve schema4 and business data')
    expected = json.loads((inputs/'expected-code.json').read_text())
    guest.check_installed(upgraded, expected, preserved=baseline)
    with zipfile.ZipFile(inputs/'fault.zip') as fault:
        fault_code = {name.removeprefix('sources/portal/'): hashlib.sha256(fault.read(name)).hexdigest()
                      for name in fault.namelist() if name.startswith('sources/portal/')}
    guest.check_installed(failed, fault_code, preserved=baseline)
    if {name: upgraded['tables'].get(name) for name in before['tables']} != before['tables'] or upgraded['documents'] != before['documents']:
        raise ValueError('Raw upgrade conservation differs')
    if json.loads(files['platform.json']) != '12.1.41.2':
        raise ValueError('Guest platform mismatch')
    return {'schema': 'vigilance.s105-native-receipt/v1', 'technical_cycle': 'COMPLETED',
            'command_count': len(commands), 'raw_archive_sha256': prepare.sha(archives[0]),
            'image_fingerprint': image['fingerprint'], 'inputs': receipt,
            'checker_control_commit': stage['checker_control_commit'], 'frozen_candidate_commit': stage['frozen_candidate_commit'],
            'baseline_import_commit': stage['baseline_import_commit'],
            'historical_equivalence': 'INDETERMINATE', 's1_05_complete': False,
            'reserve': 'Native evidence concerns reconstructed Git baseline4, not authenticated installed c9e372 content.'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--work', type=Path, required=True)
    parser.add_argument('--inputs', type=Path, required=True)
    args = parser.parse_args()
    try:
        result = collect(args.work, args.inputs)
        status = 0 if result['technical_cycle'] == 'COMPLETED' else 1
    except (OSError, ValueError, KeyError, TypeError, RuntimeError, tarfile.TarError) as error:
        result, status = {'technical_cycle': 'FAILED_OR_INCOMPLETE', 's1_05_complete': False,
                          'historical_equivalence': 'INDETERMINATE', 'error': str(error)}, 1
    (args.work/'s105-result.json').write_text(json.dumps(result, indent=2)+'\n')
    print(json.dumps(result, indent=2))
    return status


if __name__ == '__main__':
    raise SystemExit(main())
