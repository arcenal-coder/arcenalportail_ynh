"""Runner-only native actions, invoked through package_check pre/post upgrade hooks.

No action on import. This file is never part of the distributed application.
"""
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import pwd
import secrets
import subprocess
import sys
import time
import tomllib
import zipfile

ROOT = Path('/var/lib/vigilance-s105')
INSTALL = Path('/var/www/arcenalportail')
APP = 'arcenalportail'
GROUP_PREFIX = 's105_vig_'  # never collide with YunoHost's per-user primary groups


class Native:
    def __init__(self, root=ROOT):
        self.root = Path(root)
        self.evidence = self.root/'evidence'
        self.evidence.mkdir(mode=0o700, exist_ok=True)
        self.number = len(list(self.evidence.glob('command-*.json')))
        self.redactions = []

    def run(self, label, argv, *, expected=0, sensitive=False, input_text=None, timeout=900):
        self.number += 1
        started = time.time()
        try:
            result = subprocess.run(argv, input=input_text, capture_output=True, text=True, timeout=timeout)
            code, out, err = result.returncode, result.stdout, result.stderr
        except subprocess.TimeoutExpired as error:
            code, out, err = 124, str(error.stdout or ''), str(error.stderr or '')
        for secret in self.redactions:
            out, err = out.replace(secret, '<redacted>'), err.replace(secret, '<redacted>')
        name = f'command-{self.number:03}'
        (self.evidence/(name+'.stdout')).write_text(out)
        (self.evidence/(name+'.stderr')).write_text(err)
        record = {'label': label, 'argv': [argv[0], '<credentials-redacted>'] if sensitive else argv,
                  'started_utc': started, 'ended_utc': time.time(), 'returncode': code,
                  'stdout_sha256': hashlib.sha256(out.encode()).hexdigest(), 'stderr_sha256': hashlib.sha256(err.encode()).hexdigest()}
        (self.evidence/(name+'.json')).write_text(json.dumps(record, indent=2)+'\n')
        if (expected == 'nonzero' and code in (0, 124)) or (expected != 'nonzero' and code != expected):
            raise RuntimeError(f'{label}: unexpected exit {code}, see {name}')
        return out

    def probe(self, action):
        self.refresh()
        text = self.run('probe-'+action, ['runuser', '-u', APP, '--', 'env', 'S105_FIXTURE=1',
                       'ARCENAL_PORTAL_CONFIG='+str(INSTALL/'config.php'), 'php8.2', str(self.root/'probe.php'),
                       str(INSTALL), action, str(self.root/'fixture.json')])
        return json.loads(text)

    def refresh(self):
        bindings = Path('/etc/arcenal-vigilance')/APP/'bindings.json'
        if bindings.exists():
            self.run('refresh-groups', ['python3', '/usr/local/libexec/arcenal-vigilance/'+APP+'/collect_groups.py', '--config', str(bindings)])

    def state(self, name):
        result = self.probe('state')
        (self.evidence/(name+'.json')).write_text(json.dumps(result, sort_keys=True, indent=2)+'\n')
        return result

    def backup(self, name):
        self.run('backup-'+name, ['yunohost', 'backup', 'create', '--apps', APP, '--name', name, '--output-as', 'json'])
        archives = list(Path('/home/yunohost.backup/archives').glob(name+'.tar*'))
        if len(archives) != 1 or not archives[0].is_file():
            raise RuntimeError('Native backup archive missing or ambiguous')
        with archives[0].open('rb') as stream:
            digest = hashlib.file_digest(stream, 'sha256').hexdigest()
        (self.evidence/(name+'-archive.json')).write_text(json.dumps({'name': name, 'sha256': digest, 'bytes': archives[0].stat().st_size})+'\n')

    def restore(self, name):
        self.run('remove-before-'+name, ['yunohost', 'app', 'remove', APP, '--force', '--output-as', 'json'])
        self.run('restore-'+name, ['yunohost', 'backup', 'restore', name, '--apps', APP, '--force', '--output-as', 'json'])
        (INSTALL/'.maintenance').touch()
        self.run('freeze-timer', ['systemctl', 'stop', APP+'-groups.timer'])
        self.refresh()

    def provision_groups(self):
        fixture = json.loads((self.root/'fixture.json').read_text())
        roles = {'admin': ['package_checker']}
        interface = self.run('user-create-interface', ['yunohost', 'user', 'create', '--help'])
        if '--fullname' in interface:
            name_args = ['--fullname', 'S105 Fictif']
        elif '--firstname' in interface and '--lastname' in interface:
            name_args = ['--firstname', 'S105', '--lastname', 'Fictif']
        else:
            raise RuntimeError('Native user create name arguments not recognized')
        for account in fixture['accounts']:
            password = secrets.token_urlsafe(32)
            self.redactions.append(password)
            self.run('create-synthetic-user', ['yunohost', 'user', 'create', account['uid'], *name_args,
                     '--domain', self.domain, '--password', password, '--output-as', 'json'], sensitive=True)
            for role in account['roles']:
                roles.setdefault(role, []).append(account['uid'])
        for role, members in roles.items():
            group = GROUP_PREFIX+role
            self.run('create-synthetic-group', ['yunohost', 'user', 'group', 'create', group])
            for uid in members:
                self.run('add-synthetic-member', ['yunohost', 'user', 'group', 'add', group, uid])
        # Reuse the installed collector's real LDAP reader and protected writer.
        path = '/usr/local/libexec/arcenal-vigilance/'+APP+'/collect_groups.py'
        spec = importlib.util.spec_from_file_location('s105_collector', path)
        collector = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(collector)
        users, _ = collector.ldap_entries()
        identities = {}
        wanted = {'package_checker'} | {a['uid'] for a in fixture['accounts']}
        for row in users:
            attributes = collector.attributes(row)
            uid, uuid = attributes.get('uid', []), attributes.get('entryuuid', [])
            if len(uid) == 1 and uid[0] in wanted and len(uuid) == 1:
                identities[uid[0]] = uuid[0]
        if set(identities) != wanted:
            raise RuntimeError('Synthetic LDAP identities missing')
        bindings = {'schema': 'vigilance.group-bindings/v1', 'instance': APP, 'company_id': self.company,
                    'groups': {role: [GROUP_PREFIX+role] for role in roles}, 'legacy_bindings': identities}
        collector.bindings(json.dumps(bindings).encode())
        collector.publish(bindings, Path('/etc/arcenal-vigilance')/APP/'bindings.json', pwd.getpwnam(APP).pw_gid)
        self.probe('group-config')
        self.refresh()
        self.run('activate-real-groups', ['runuser', '-u', APP, '--', 'env', 'ARCENAL_PORTAL_CONFIG='+str(INSTALL/'config.php'),
                                        'php8.2', str(INSTALL/'bin/manage.php'), 'auth-activate', '--apply'])

    def pre(self):
        if tomllib.loads(Path('/etc/yunohost/apps/arcenalportail/manifest.toml').read_text())['version'] != '1.1.0-beta6~ynh1':
            raise RuntimeError('Expected the added beta6/schema4 scenario')
        version = self.run('platform', ['dpkg-query', '-W', '-f=${Version}', 'yunohost']).strip()
        (self.evidence/'platform.json').write_text(json.dumps(version, indent=2)+'\n')
        architecture = self.run('architecture', ['dpkg', '--print-architecture']).strip()
        if version != '12.1.41.2' or architecture != 'amd64':
            raise RuntimeError('Required platform is 12.1.41.2/amd64; observed '+version+'/'+architecture)
        help_text = self.run('upgrade-interface', ['yunohost', 'app', 'upgrade', '--help'])
        if not all(flag in help_text for flag in ('--file', '--force', '--no-safety-backup')):
            raise RuntimeError('Native upgrade interface not recognized')
        self.domain = self.run('domain', ['yunohost', 'app', 'setting', APP, 'domain']).strip()
        self.company = self.probe('context')['company_id']
        if self.run('cron-state', ['systemctl', 'show', 'cron', '--property=ActiveState', '--value']).strip() == 'active':
            (self.root/'cron-was-active').touch()
        self.run('freeze-cron', ['systemctl', 'stop', 'cron'])
        self.run('freeze-timer', ['systemctl', 'stop', APP+'-groups.timer'])
        (INSTALL/'.maintenance').touch()
        self.probe('seed4')
        self.provision_groups()
        before = self.state('before4')
        if len(before['schema']) != 4:
            raise RuntimeError('Not schema4')
        if before['code'] != json.loads((self.root/'baseline-code.json').read_text()):
            raise RuntimeError('Installed baseline code differs from frozen Git import')
        self.backup('s105-schema4')
        self.restore('s105-schema4')
        compare_state(before, self.state('restored4'))
        (self.root/'pre-ready').write_text('schema4 checkpoint and restore completed\n')

    def post(self):
        before = json.loads((self.evidence/'before4.json').read_text())
        upgraded = self.state('upgraded6')
        if len(upgraded['schema']) != 6 or upgraded['schema'][:4] != before['schema']:
            raise RuntimeError('Upgrade migration/checksum mismatch')
        for name, value in before['tables'].items():
            if upgraded['tables'].get(name) != value:
                raise RuntimeError('Pre-existing business/mail table changed: '+name)
        if upgraded['documents'] != before['documents']:
            raise RuntimeError('Pre-existing documents changed')
        expected = json.loads((self.root/'expected-code.json').read_text())
        if upgraded['code'] != expected:
            raise RuntimeError('Installed managed code differs from frozen candidate')
        self.probe('target6')
        self.probe('verify6')
        state6 = self.state('before6')
        self.backup('s105-schema6')
        self.restore('s105-schema6')
        compare_state(state6, self.state('restored6'))
        self.probe('verify6')
        # Fault occurs only on a freshly restored, closed schema4 checkpoint.
        self.restore('s105-schema4')
        compare_state(before, self.state('failure-before4'))
        inputs = json.loads((self.root/'inputs.json').read_text())
        if hashlib.sha256((self.root/'fault.zip').read_bytes()).hexdigest() != inputs['fault_sha256']:
            raise RuntimeError('Fault ZIP transport hash mismatch')
        self.run('faulty-native-upgrade', ['yunohost', 'app', 'upgrade', APP, '--file', str(self.root/'fault.zip'),
                 '--force', '--no-safety-backup', '--output-as', 'json'], expected='nonzero')
        closed = (INSTALL/'.maintenance').exists()
        self.run('failure-maintenance', ['stat', '-c', '%a %U %G %n', str(INSTALL/'.maintenance')])
        # Reject generic CLI/download failures: managed code must actually be the
        # faulty copy, and its real migrate entrypoint must fail transactionally.
        self.run('fault-confirm-migrate', ['runuser', '-u', APP, '--', 'env', 'ARCENAL_PORTAL_CONFIG='+str(INSTALL/'config.php'),
                                         'php8.2', str(INSTALL/'bin/manage.php'), 'migrate'], expected=1)
        failed = self.state('failed-migration4')
        with zipfile.ZipFile(self.root/'fault.zip') as fault:
            fault_code = {name.removeprefix('sources/portal/'): hashlib.sha256(fault.read(name)).hexdigest()
                          for name in fault.namelist() if name.startswith('sources/portal/')}
        if failed['code'] != fault_code:
            raise RuntimeError('Failure occurred before installing the faulty managed code')
        if not closed or failed['schema'] != before['schema'] or 's105_fault_marker' in failed['tables'] or 'affair' in failed['tables']:
            raise RuntimeError('Failed migration was not atomic or did not leave the app closed')
        self.restore('s105-schema4')
        compare_state(before, self.state('recovered4'))
        # Leave the checker with the real target6, not with a downgraded app.
        self.restore('s105-schema6')
        compare_state(state6, self.state('final6'))
        self.probe('verify6')
        self.probe('finish-mail')
        self.run('native-modes', ['stat', '-c', '%a %U %G %n', str(INSTALL), str(INSTALL/'config.php'), '/etc/cron.d/'+APP+'-journey-mail'])
        self.run('enable-timer', ['systemctl', 'start', APP+'-groups.timer'])
        self.run('timer-status', ['systemctl', 'is-active', APP+'-groups.timer'])
        self.run('group-service', ['systemctl', 'start', APP+'-groups.service'])
        self.run('php-service', ['systemctl', 'is-active', 'php8.2-fpm'])
        self.run('nginx-service', ['systemctl', 'is-active', 'nginx'])
        (INSTALL/'.maintenance').unlink()
        if (self.root/'cron-was-active').exists():
            self.run('restore-cron', ['systemctl', 'start', 'cron'])
        # Native mail transport itself remains the existing package_check guard.
        files = {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in self.evidence.iterdir() if p.is_file()}
        (self.evidence/'completed.json').write_text(json.dumps({'technical_cycle': 'COMPLETED', 'files': files,
                         'historical_equivalence': 'INDETERMINATE', 's1_05_complete': False}, indent=2)+'\n')
        (self.root/'pre-ready').unlink()


def compare_state(expected, actual):
    # Deliberately exclude volatile projection.json; real collector refresh is logged.
    for key in ('schema', 'tables', 'documents', 'code', 'permissions', 'config'):
        if actual.get(key) != expected.get(key):
            raise RuntimeError('Restoration differs: '+key)


def main():
    if os.environ.get('S105_NATIVE_RUNNER') != '1' or os.geteuid() != 0 or not Path('/etc/yunohost/apps/arcenalportail').is_dir():
        raise SystemExit('Runner-only native hook; not permitted on this machine')
    if len(sys.argv) != 3 or sys.argv[2] != APP or sys.argv[1] not in ('pre', 'post'):
        raise SystemExit('Unexpected hook arguments')
    native = Native()
    # Reuse existing native mail confinement, held across each whole hook.
    spec = importlib.util.spec_from_file_location('existing_mail', ROOT/'native_mail_check.py')
    if not (ROOT/'native_mail_check.py').exists():
        # Preupgrade bootstrap supplies the exact existing helper as well.
        raise SystemExit('Existing native mail guard missing')
    helper = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(helper)
    try:
        with helper.blocked_smtp_egress():
            native.run('smtp-confinement', ['nft', 'list', 'table', 'inet', 'vigilance_mail_'+str(os.getpid())])
            getattr(native, sys.argv[1])()
    except Exception:
        # Fail closed while preserving evidence; no automatic recovery concealing a failure.
        if INSTALL.exists():
            (INSTALL/'.maintenance').touch()
        raise


if __name__ == '__main__':
    main()
