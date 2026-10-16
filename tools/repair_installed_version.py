"""Repair only the invalid VIGIlance beta1/beta2 version label, with backup.

No application code, settings, roles, database or document is modified.
Default mode is read-only; --apply requires root on the YunoHost server.
"""
import argparse
import os
import re
import stat
import tempfile
import tomllib
from pathlib import Path

VERSIONS = {"1.1.0~beta1+ynh1": "1.1.0-beta1~ynh1", "1.1.0~beta2+ynh1": "1.1.0-beta2~ynh1"}


def repair(manifest, apply=False):
    manifest = Path(manifest)
    if manifest.is_symlink():
        raise ValueError("Manifest symbolique refusé.")
    original_bytes = manifest.read_bytes()
    content = original_bytes.decode('utf-8')
    data = tomllib.loads(content)
    if data.get("id") != "arcenalportail":
        raise ValueError("Ce manifest ne correspond pas à VIGIlance.")
    old = data.get("version", "")
    if old not in VERSIONS:
        return {"version": old, "change": False}
    new = VERSIONS[old]
    pattern = re.compile(r'(?m)^(\s*version\s*=\s*)([\x22\x27])' + re.escape(old) + r'\2([ \t]*(?:#.*)?)$')
    updated, count = pattern.subn(lambda match: match[1] + match[2] + new + match[2] + match[3], content)
    if count != 1 or tomllib.loads(updated).get("version") != new:
        raise ValueError("Ligne de version ambiguë ; aucune modification.")
    if apply:
        backup = manifest.with_name(manifest.name + ".vigilance-version-backup")
        if backup.is_symlink():
            raise ValueError("Sauvegarde symbolique refusée.")
        try:
            backup_fd = os.open(backup, os.O_RDONLY | os.O_NOFOLLOW)
        except FileNotFoundError:
            backup_fd = os.open(backup, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(backup_fd, 'wb') as stream:
                stream.write(original_bytes)
                stream.flush()
                os.fsync(stream.fileno())
        else:
            with os.fdopen(backup_fd, 'rb') as stream:
                if not stat.S_ISREG(os.fstat(stream.fileno()).st_mode) or stream.read() != original_bytes:
                    raise ValueError("Une sauvegarde différente existe déjà ; contrôle manuel nécessaire.")
                os.fchmod(stream.fileno(), 0o600)
        info = manifest.stat()
        fd, temporary = tempfile.mkstemp(prefix=".vigilance-version-", dir=manifest.parent)
        try:
            with os.fdopen(fd, "wb") as stream:
                stream.write(updated.encode('utf-8'))
                stream.flush()
                os.fsync(stream.fileno())
            os.chmod(temporary, info.st_mode & 0o777)
            if os.geteuid() == 0:
                os.chown(temporary, info.st_uid, info.st_gid)
            os.replace(temporary, manifest)
        finally:
            if os.path.exists(temporary):
                os.unlink(temporary)
    return {"version": old, "new_version": new, "change": True, "applied": apply}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--app", default="arcenalportail")
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    if not re.fullmatch(r"arcenalportail(?:__[0-9]+)?", args.app):
        raise SystemExit("Identifiant d'application refusé.")
    if args.apply and os.geteuid() != 0:
        raise SystemExit("--apply doit être exécuté par l'administrateur système.")
    directory = Path("/etc/yunohost/apps") / args.app
    if directory.is_symlink():
        raise SystemExit("Dossier symbolique refusé.")
    try:
        result = repair(directory / "manifest.toml", args.apply)
    except (OSError, ValueError) as error:
        raise SystemExit(str(error)) from error
    if result["change"]:
        print(f"{args.app}: {result['version']} → {result['new_version']} — " + ("corrigé, sauvegarde conservée" if args.apply else "simulation, aucune modification"))
    else:
        print(f"{args.app}: version {result['version']} inchangée.")


if __name__ == "__main__":
    main()
