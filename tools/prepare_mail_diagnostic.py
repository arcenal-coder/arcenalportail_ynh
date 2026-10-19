"""Runner-only single-upgrade diagnostic. Never emits qualification evidence."""
import re
import sys
import tomllib
from pathlib import Path


def prepare(path):
    path = Path(path)
    text = path.read_text()
    config = tomllib.loads(text)
    if 'only' in config['default'] or 'exclude' in config['default']:
        raise ValueError('Unexpected existing test restriction')
    text = re.sub(r'^test_upgrade_from\..*\n', '', text, flags=re.M)
    # The pinned parser forbids "only" on default and uses "upgrade" aliases.
    text = text.replace('[default]', '[mail_diagnostic]', 1).replace('[default.', '[mail_diagnostic.')
    text = text.replace('[mail_diagnostic]\n', '[mail_diagnostic]\nonly = ["install.root", "upgrade"]\n', 1)
    text += '\n[default]\nexclude = ["package_linter", "install.root", "install.subdir", "install.nourl", "install.multi", "backup_restore", "upgrade", "change_url"]\n'
    assert tomllib.loads(text)['mail_diagnostic']['only'] == ['install.root', 'upgrade']
    path.write_text(text)


if __name__ == '__main__':
    prepare(sys.argv[1])
