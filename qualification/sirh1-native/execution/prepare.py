"""Prepare immutable inputs and a package_check overlay. No network or native CLI."""
from pathlib import Path
import argparse
import base64
import hashlib
import io
import json
import os
import re
import shutil
import stat
import subprocess
import sys
import tomllib
import zipfile
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import control

HERE = Path(__file__).resolve().parent
GIT = '/opt/homebrew/bin/git' if Path('/opt/homebrew/bin/git').is_file() else 'git'
POST_ANCHOR = '    if [ "$ret" -eq 0 ]; then\n        local mail_result'
BASELINE_SHA = 'dec344d4fbdfe5c849f4eb649acffb25450f541d1b846d77c0cb7c4873c6f1fd'
FAULT_FILE = 'sources/portal/migrations/006-mission-work-membership.sql'
FAULT_SQL = b'\nCREATE TABLE s105_fault_marker(id INTEGER);\nS105_INTENTIONAL_INVALID_SQL;\n'


def git(directory, *args, data=None, env=None):
    return subprocess.run([GIT, '-C', str(directory), *args], input=data, capture_output=True,
                          check=True, timeout=60, env=env).stdout


def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def build_inputs(root, destination):
    root, destination = Path(root), Path(destination)
    inventory = json.loads((root/'.arcenal/recette-sirh1/proposition-ci-2026-10-28/baseline-4cae330-files.json').read_text())
    control.inspect_baseline(inventory)
    candidate = root/'arcenal-qsse/dist/arcenalportail_ynh-1.1.0-beta.7-ynh3.zip'
    control.inspect_candidate(candidate)
    # Validate every object before creating output; never fetch a missing object.
    bodies = {}
    for entry in inventory['entries']:
        body = git(root, 'show', f"{control.BASELINE_COMMIT}:arcenal-qsse/portal_ynh/{entry['path']}")
        if len(body) != entry['bytes'] or hashlib.sha256(body).hexdigest() != entry['sha256']:
            raise ValueError('Git baseline object differs: ' + entry['path'])
        bodies[entry['path']] = body
    destination.mkdir(parents=True, exist_ok=False)
    with zipfile.ZipFile(destination/'baseline.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
        for entry in inventory['entries']:
            info = zipfile.ZipInfo(entry['path'], (2026, 9, 8, 0, 0, 0))
            info.external_attr = int(entry['zip_mode'], 8) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, bodies[entry['path']])
    if sha(destination/'baseline.zip') != BASELINE_SHA:
        raise ValueError('Reconstructed baseline ZIP differs from the reviewed receipt')
    shutil.copyfile(candidate, destination/'candidate.zip')
    with zipfile.ZipFile(candidate) as source, zipfile.ZipFile(destination/'fault.zip', 'w') as target:
        for entry in source.infolist():
            body = source.read(entry)
            target.writestr(entry, body + FAULT_SQL if entry.filename == FAULT_FILE else body)
    result = {'candidate_sha256': sha(destination/'candidate.zip'), 'baseline_sha256': sha(destination/'baseline.zip'),
              'fault_sha256': sha(destination/'fault.zip'), 'baseline_git': control.BASELINE_COMMIT,
              'historical_equivalence': 'INDETERMINATE', 'fault_delta': FAULT_FILE}
    (destination/'inputs.json').write_text(json.dumps(result, indent=2)+'\n')
    with zipfile.ZipFile(candidate) as archive:
        expected = {name.removeprefix('sources/portal/'): hashlib.sha256(archive.read(name)).hexdigest()
                    for name in archive.namelist() if name.startswith('sources/portal/')}
    (destination/'expected-code.json').write_text(json.dumps(expected, sort_keys=True, indent=2)+'\n')
    baseline_code = {name.removeprefix('sources/portal/'): hashlib.sha256(body).hexdigest()
                     for name, body in bodies.items() if name.startswith('sources/portal/')}
    (destination/'expected-baseline-code.json').write_text(json.dumps(baseline_code, sort_keys=True, indent=2)+'\n')
    verify_inputs(destination)
    return result


def verify_inputs(inputs):
    inputs = Path(inputs)
    control.inspect_candidate(inputs/'candidate.zip')
    if sha(inputs/'baseline.zip') != BASELINE_SHA:
        raise ValueError('Wrong reconstructed schema4 baseline')
    receipt = json.loads((inputs/'inputs.json').read_text())
    if receipt['candidate_sha256'] != control.CANDIDATE_SHA or receipt['baseline_sha256'] != BASELINE_SHA or receipt['fault_sha256'] != sha(inputs/'fault.zip'):
        raise ValueError('Input receipt mismatch')
    with zipfile.ZipFile(inputs/'candidate.zip') as candidate, zipfile.ZipFile(inputs/'fault.zip') as fault:
        if candidate.namelist() != fault.namelist():
            raise ValueError('Fault copy paths differ')
        for entry in candidate.infolist():
            other = fault.getinfo(entry.filename)
            expected = candidate.read(entry)+(FAULT_SQL if entry.filename == FAULT_FILE else b'')
            if fault.read(entry.filename) != expected or entry.external_attr != other.external_attr:
                raise ValueError('Unexpected fault delta: '+entry.filename)
        expected = {name.removeprefix('sources/portal/'): hashlib.sha256(candidate.read(name)).hexdigest()
                    for name in candidate.namelist() if name.startswith('sources/portal/')}
    if json.loads((inputs/'expected-code.json').read_text()) != expected:
        raise ValueError('Expected installed code map differs from frozen ZIP')
    with zipfile.ZipFile(inputs/'baseline.zip') as baseline:
        expected_baseline = {name.removeprefix('sources/portal/'): hashlib.sha256(baseline.read(name)).hexdigest()
                             for name in baseline.namelist() if name.startswith('sources/portal/')}
    if json.loads((inputs/'expected-baseline-code.json').read_text()) != expected_baseline:
        raise ValueError('Expected baseline code map differs from reconstructed ZIP')
    return receipt


def patch_checker(directory):
    path = Path(directory)/'lib/tests.sh'
    source = path.read_text()
    if source.count(POST_ANCHOR) != 1 or 's105_after_upgrade' in source:
        raise ValueError('Pinned post-upgrade mail anchor not recognized; no write')
    hook = '''    # S1-05 only when the additional beta6 preupgrade left its marker.
    source "$S105_BRIDGE" || return 1
    if [ "$ret" -eq 0 ]; then
        s105_after_upgrade || ret=1
    else
        s105_collect || ret=1
    fi

'''
    path.write_text(source.replace(POST_ANCHOR, hook+POST_ANCHOR))


def plan_overlay(original, baseline_commit, baseline_code=None, mail_source=None):
    if re.fullmatch('[0-9a-f]{40}', baseline_commit) is None:
        raise ValueError('Invalid baseline commit')
    before = tomllib.loads(original)['default']
    if baseline_commit in before['test_upgrade_from']:
        raise ValueError('Baseline scenario already present')
    payload = {name: base64.b64encode((HERE/name).read_bytes()).decode() for name in ('guest.py', 'probe.php')}
    project = HERE.parents[2]
    tools = project/'arcenal-qsse/portal_ynh/tools'
    if not tools.is_dir():
        tools = project/'tools'  # public package checkout, after explicit authorization
    payload['native_mail_check.py'] = base64.b64encode(mail_source if mail_source is not None else (tools/'native_mail_check.py').read_bytes()).decode()
    if baseline_code is None:
        inventory = json.loads((project/'.arcenal/recette-sirh1/proposition-ci-2026-10-28/baseline-4cae330-files.json').read_text())
        control.inspect_baseline(inventory)
        baseline_code = {e['path'].removeprefix('sources/portal/'): e['sha256'] for e in inventory['entries'] if e['path'].startswith('sources/portal/')}
    payload['baseline-code.json'] = base64.b64encode(json.dumps(baseline_code, sort_keys=True).encode()).decode()
    payload['fixture.json'] = base64.b64encode((HERE.parent/'fixtures/synthetic-data.json').read_bytes()).decode()
    bootstrap = '''import base64,json,os,pathlib,subprocess,sys,tomllib
app=sys.argv[1]
if app!='arcenalportail': sys.exit(0)
manifest=pathlib.Path('/etc/yunohost/apps')/app/'manifest.toml'
if tomllib.loads(manifest.read_text()).get('version')!='1.1.0-beta6~ynh1': sys.exit(0)
root=pathlib.Path('/var/lib/vigilance-s105')
if root.exists(): raise RuntimeError('S105 state already exists')
root.mkdir(mode=0o755)
for name,body in PAYLOAD.items():
 p=root/name;p.write_bytes(base64.b64decode(body));p.chmod(0o644)
subprocess.run(['python3',str(root/'guest.py'),'pre',app],env=dict(os.environ,S105_NATIVE_RUNNER='1'),check=True)
'''.replace('PAYLOAD', repr(payload))
    command = 'python3 -c '+json.dumps('import base64;exec(base64.b64decode('+repr(base64.b64encode(bootstrap.encode()).decode())+'))')+' "$APP"'
    # JSON double-quoted strings are compatible with this TOML basic-string field.
    updated, count = re.subn(r'(?m)^preupgrade = .*$', lambda _: 'preupgrade = '+json.dumps(before['preupgrade']+'\nset +x\n'+command+'\n'), original)
    if count != 1 or original.count('[default.curl_tests]') != 1:
        raise ValueError('Existing test plan layout not recognized')
    line = 'test_upgrade_from.'+baseline_commit+'.name = "S1-05 depuis import beta6/schema4 (équivalence historique indéterminée)"\n\n'
    return updated.replace('[default.curl_tests]', line+'[default.curl_tests]')


def copy_tools(source, destination):
    """Minimal group-compatible adapter in the runner copy, never the active tool."""
    destination = Path(destination)
    shutil.copytree(source, destination, ignore=shutil.ignore_patterns('__pycache__', '*.pyc'))
    path = destination/'native_mail_check.py'
    text = path.read_text()
    original = "$account['roles'],$account['teams'],true,(bool)$account['directory']"
    if text.count(original) != 1:
        raise ValueError('Existing mail admin seam not recognized')
    path.write_text(text.replace(original, "($a->grouped()?[]:$account['roles']),$account['teams'],true,(bool)$account['directory']"))


def extract(archive, directory):
    directory = Path(directory)
    with zipfile.ZipFile(archive) as source:
        entries = source.infolist()
        for entry in entries:
            parts = Path(entry.filename).parts
            if not parts or Path(entry.filename).is_absolute() or '..' in parts or stat.S_ISLNK(entry.external_attr >> 16):
                raise ValueError('Unsafe archive member')
        directory.mkdir(parents=True, exist_ok=False)
        for entry in entries:
            if entry.is_dir():
                continue
            path = directory/entry.filename
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(source.read(entry))
            path.chmod((entry.external_attr >> 16) & 0o777 or 0o644)


def import_tree(repository, archive, parent, overlay=None):
    """Import bytes/modes with an isolated Git index, without repacking the ZIP."""
    index = Path(repository)/'.git/s105-import-index'
    if index.exists():
        raise ValueError('Import index already exists')
    env = dict(os.environ, GIT_INDEX_FILE=str(index), GIT_AUTHOR_NAME='S105 fixture', GIT_AUTHOR_EMAIL='fixture@example.invalid',
               GIT_COMMITTER_NAME='S105 fixture', GIT_COMMITTER_EMAIL='fixture@example.invalid')
    try:
        git(repository, 'read-tree', '--empty', env=env)
        with zipfile.ZipFile(archive) as source:
            for entry in source.infolist():
                if entry.is_dir() or '\n' in entry.filename or '\t' in entry.filename or entry.filename.startswith('/') or '..' in Path(entry.filename).parts:
                    raise ValueError('Non-file or unsafe import member')
                body = overlay.encode() if overlay is not None and entry.filename == 'tests.toml' else source.read(entry)
                oid = git(repository, 'hash-object', '-w', '--stdin', data=body).decode().strip()
                mode = '100755' if entry.external_attr >> 16 & 0o111 else '100644'
                git(repository, 'update-index', '--add', '--cacheinfo', mode+','+oid+','+entry.filename, env=env)
        tree = git(repository, 'write-tree', env=env).decode().strip()
        return git(repository, 'commit-tree', tree, '-p', parent, data=b'S105 frozen import / control overlay\n', env=env).decode().strip()
    finally:
        index.unlink(missing_ok=True)


def stage(inputs, history, output):
    inputs, history, output = Path(inputs).resolve(), Path(history).resolve(), Path(output).resolve()
    receipt = verify_inputs(inputs)
    # Existing full package history only; no remote operation or fetch fallback.
    for kind, commit in control.REQUIRED:
        if kind == 'TEST_UPGRADE' and commit:
            git(history, 'cat-file', '-e', commit+'^{commit}')
    if output.exists():
        raise ValueError('Stage output must be new')
    subprocess.run([GIT, 'clone', '--local', '--no-hardlinks', '--no-checkout', str(history), str(output)], check=True, timeout=60)
    parent = git(output, 'rev-parse', 'HEAD').decode().strip()
    baseline = import_tree(output, inputs/'baseline.zip', parent)
    frozen = import_tree(output, inputs/'candidate.zip', baseline)
    with zipfile.ZipFile(inputs/'candidate.zip') as source:
        overlay = plan_overlay(source.read('tests.toml').decode(), baseline,
                               json.loads((inputs/'expected-baseline-code.json').read_text()),
                               source.read('tools/native_mail_check.py'))
    checked = import_tree(output, inputs/'candidate.zip', frozen, overlay)
    git(output, 'update-ref', 'refs/heads/s105-baseline', baseline)
    git(output, 'update-ref', 'refs/heads/s105-frozen', frozen)
    git(output, 'checkout', '--detach', checked)
    receipt.update(baseline_import_commit=baseline, frozen_candidate_commit=frozen, checker_control_commit=checked,
                   checker_only_overlay=['tests.toml'], control_commit=parent)
    return receipt


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    subs = parser.add_subparsers(dest='command', required=True)
    build = subs.add_parser('build-inputs')
    build.add_argument('--root', type=Path, required=True)
    build.add_argument('--output', type=Path, required=True)
    staging = subs.add_parser('stage')
    staging.add_argument('--inputs', type=Path, required=True)
    staging.add_argument('--history', type=Path, required=True)
    staging.add_argument('--output', type=Path, required=True)
    patch = subs.add_parser('patch-checker')
    patch.add_argument('checker', type=Path)
    tools = subs.add_parser('copy-tools')
    tools.add_argument('--source', type=Path, required=True)
    tools.add_argument('--output', type=Path, required=True)
    verify = subs.add_parser('verify-inputs')
    verify.add_argument('--inputs', type=Path, required=True)
    args = parser.parse_args()
    if args.command == 'build-inputs':
        result = build_inputs(args.root, args.output)
    elif args.command == 'stage':
        result = stage(args.inputs, args.history, args.output)
    elif args.command == 'patch-checker':
        patch_checker(args.checker)
        result = {'hook_added': True}
    elif args.command == 'copy-tools':
        copy_tools(args.source, args.output)
        result = {'tools_copy': str(args.output)}
    else:
        result = verify_inputs(args.inputs)
    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
