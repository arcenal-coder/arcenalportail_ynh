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
    text = text.replace('[default]\n', '[default]\nonly = ["TEST_UPGRADE"]\n', 1)
    assert tomllib.loads(text)['default']['only'] == ['TEST_UPGRADE']
    path.write_text(text)


if __name__ == '__main__':
    prepare(sys.argv[1])
