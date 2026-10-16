"""Keep native tests intact; use HTTPS preflight when a runner blocks ICMP."""
from pathlib import Path
import sys


def prepare(directory):
    path = Path(directory) / "lib/common.sh"
    source = path.read_text()
    original = "    ping -q -c 2 yunohost.org > /dev/null 2>&1 \\\n"
    replacement = "    curl --fail --silent --show-error --proto '=https' --max-time 20 https://repo.yunohost.org/incus/streams/v1/index.json > /dev/null 2>&1 \\\n    || ping -q -c 2 yunohost.org > /dev/null 2>&1 \\\n"
    if source.count(original) != 1:
        raise ValueError("Préflight amont non reconnu ; aucun test modifié.")
    path.write_text(source.replace(original, replacement))


if __name__ == "__main__":
    prepare(sys.argv[1])
