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
    script = Path(directory) / "package_check.sh"
    source = script.read_text()
    original = '    [ -n "$TEST_CONTEXT" ] && rm -rf "$TEST_CONTEXT"\n'
    preserve = '''    # Preserve raw JSON evidence before the normal upstream cleanup.
    if [[ -n "$TEST_CONTEXT" && -d "$TEST_CONTEXT/results" ]]; then
        evidence="$PWD/arcenal-evidence/$WORKER_ID"
        mkdir -p "$evidence"
        cp -a "$TEST_CONTEXT/tests" "$TEST_CONTEXT/results" "$TEST_CONTEXT/commit" "$evidence/"
    fi
'''
    if source.count(original) != 1:
        raise ValueError("Nettoyage amont non reconnu ; aucune qualification possible.")
    script.write_text(source.replace(original, preserve + original))


if __name__ == "__main__":
    prepare(sys.argv[1])
