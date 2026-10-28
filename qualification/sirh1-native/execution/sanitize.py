"""Bounded, fail-closed copies for fixture logs; never print input on error."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import re
import shlex
import sys
import tarfile

LIMIT = 32_000_000
MARKER = re.compile(r'(?i)\b(?:YUNO_PWD|PASSWORD|passwd|token|private_key|gateway_key)\b\s*[=:]')
ASSIGNMENT = re.compile(r'''(?i)\b(?:YUNO_PWD|PASSWORD|passwd|token|private_key|gateway_key)\b["']?\s*[=:]\s*("[^"\n]*"|'[^'\n]*'|[^\s,}]+)''')


def decode(body):
    if len(body) > LIMIT or b'\x00' in body:
        raise ValueError('Unsafe or oversized evidence')
    return body.decode('utf-8', errors='strict')


def discover(text):
    values = set()
    for match in ASSIGNMENT.finditer(text):
        try:
            parts = shlex.split(match[1])
        except ValueError:
            raise ValueError('Unparseable credential assignment') from None
        if parts and parts[0] and not parts[0].startswith('$') and parts[0] not in {'[REDACTED]', '<redacted>'}:
            values.add(parts[0])
    return values


def scrub(body, values):
    text = decode(body)
    for value in sorted(values, key=len, reverse=True):
        text = text.replace(value, '[REDACTED]')
    # Preserve structured JSON; scrub credential fields recursively.
    try:
        obj = json.loads(text)
    except json.JSONDecodeError:
        text = ''.join('[REDACTED CREDENTIAL LINE]\n' if MARKER.search(line) else line
                       for line in text.splitlines(keepends=True))
    else:
        def clean(value):
            if isinstance(value, dict):
                return {k: '[REDACTED]' if MARKER.match(k+'=') else clean(v) for k, v in value.items()}
            if isinstance(value, list):
                return [clean(v) for v in value]
            return value
        cleaned = clean(obj)
        if cleaned != obj:
            text = json.dumps(cleaned, ensure_ascii=False)+'\n'
    if any(value in text for value in values):
        raise ValueError('Residual credential in evidence')
    return text.encode()


def tar_members(body):
    files = {}
    with tarfile.open(fileobj=io.BytesIO(body), mode='r:gz') as archive:
        total = 0
        for member in archive:
            parts = Path(member.name).parts
            if member.isdir() and member.name == 'evidence':
                continue
            if not member.isfile() or len(parts) != 2 or parts[0] != 'evidence' or member.name in files:
                raise ValueError('Unsafe guest archive')
            total += member.size
            if member.size > 4_000_000 or total > LIMIT:
                raise ValueError('Oversized guest archive')
            files[member.name] = archive.extractfile(member).read()
    return files


def clean_tar(files, values):
    cleaned = {name: scrub(body, values) for name, body in files.items()}
    # Bind sanitized command receipts to sanitized streams, not the raw bytes.
    for name, body in list(cleaned.items()):
        if re.fullmatch(r'evidence/command-\d+\.json', name):
            receipt = json.loads(body)
            for suffix in ('stdout', 'stderr'):
                stream = name[:-4]+suffix
                if stream in cleaned:
                    receipt[suffix+'_sha256'] = hashlib.sha256(cleaned[stream]).hexdigest()
            cleaned[name] = json.dumps(receipt).encode()
    completion = 'evidence/completed.json'
    if completion in cleaned:
        receipt = json.loads(cleaned[completion])
        receipt['files'] = {Path(n).name: hashlib.sha256(b).hexdigest() for n, b in cleaned.items() if n != completion}
        cleaned[completion] = json.dumps(receipt).encode()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz') as archive:
        for name, body in cleaned.items():
            member = tarfile.TarInfo(name)
            member.size = len(body)
            member.mode = 0o600
            archive.addfile(member, io.BytesIO(body))
    return output.getvalue()


def stage_upload(raw, output):
    raw, output = Path(raw), Path(output)
    if output.exists():
        raise ValueError('Upload directory must be new')
    # Only the evidence tree and explicit metadata/log files, never a checkout,
    # database, configuration file, original ZIP or base64 transport.
    payload = {}
    for prefix in ('s105', 'package_check', 's105-platform'):
        base = raw/prefix
        if base.is_symlink():
            raise ValueError('Linked evidence root')
        if not base.exists():
            continue
        for path in base.rglob('*'):
            if path.is_symlink():
                raise ValueError('Linked evidence member')
            if not path.is_file() or path.suffix == '.b64':
                continue
            rel = path.relative_to(raw)
            name = path.name
            allowed = (prefix == 's105' and (rel.parts[1] == 'evidence' or name in {'stage.json', 's105-result.json', 'package-qualification.json', 'linter.json', 'package-plan.json', 'tests.sh.used', 'native_mail_check.py.used', 'checker.log'})) or (prefix == 's105-platform' and name in {'alias.json', 'image.json'}) or (prefix == 'package_check' and (name.startswith(('full_log_', 'results_')) and path.suffix in {'.log', '.json'} or 'arcenal-evidence' in rel.parts and path.suffix == '.json'))
            if not allowed:
                continue
            if path.stat().st_size > LIMIT:
                raise ValueError('Oversized evidence file')
            payload[str(rel)] = path.read_bytes()
    if not payload or len(payload) > 4096 or sum(map(len, payload.values())) > 4*LIMIT:
        raise ValueError('Empty or oversized upload')
    expanded = {n: tar_members(b) for n, b in payload.items() if n.endswith('.tar.gz')}
    if sum(len(b) for files in expanded.values() for b in files.values()) > 4*LIMIT:
        raise ValueError('Expanded evidence bound exceeded')
    values = set()
    for name, body in payload.items():
        for text in (expanded[name].values() if name in expanded else [body]):
            values.update(discover(decode(text)))
    cleaned = {n: clean_tar(expanded[n], values) if n in expanded else scrub(b, values) for n, b in payload.items()}
    if any(value in name for value in values for name in cleaned):
        raise ValueError('Credential in evidence filename')
    # No writes until every member has passed parsing and sanitation.
    output.mkdir(mode=0o700)
    hashes = {}
    for name, body in cleaned.items():
        target = output/name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(body)
        hashes[name] = hashlib.sha256(body).hexdigest()
    (output/'upload-verified.json').write_text(json.dumps({'sanitized_files': hashes,
        'source_sha256': {n: hashlib.sha256(b).hexdigest() for n, b in payload.items()},
        'raw_transport_uploaded': False, 'credential_values_retained': False,
        'note': 'Sanitized derivatives; command/completion hashes rebound after redaction.'}, indent=2)+'\n')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['stream', 'upload'])
    parser.add_argument('--raw', type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    try:
        if args.command == 'stream':
            # Buffer before emitting: a later assignment may identify an earlier echo.
            body = sys.stdin.buffer.read(LIMIT+1)
            text = decode(body)
            values = discover(text)
            if os.environ.get('S105_SECRET'):
                values.add(os.environ['S105_SECRET'])
            sys.stdout.buffer.write(scrub(body, values))
        else:
            stage_upload(args.raw, args.output)
    except (OSError, ValueError, UnicodeError, tarfile.TarError):
        print('Evidence sanitation failed; no raw fallback.', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
