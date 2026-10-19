"""Keep native tests intact; use HTTPS preflight when a runner blocks ICMP."""
from pathlib import Path
import base64
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
    # Add a read-only post-upgrade security assertion. The original legacy
    # install flags remain in the report and are not cleared or rewritten.
    tests = Path(directory) / "lib/tests.sh"
    source = tests.read_text()
    original = '    ret=$?\n\n    metrics_stop\n\n    return $ret\n}\n\nTEST_PORT_ALREADY_USED()'
    checked = '''    ret=$?

    if [ "$ret" -eq 0 ]; then
        local installed_mode installed_mode_raw
        installed_mode_raw=$(LXC_EXEC "stat -c %a /var/www/$app_id") || ret=1
        installed_mode=${installed_mode_raw%$'\\r'}
        if [ "$ret" -eq 0 ]; then
            jq --arg mode "$installed_mode" --arg raw "$installed_mode_raw" '. + {arc_post_upgrade_install_mode:$mode,arc_post_upgrade_install_mode_raw:$raw}' "$current_test_results" > "$current_test_results.mode"
            mv "$current_test_results.mode" "$current_test_results"
            [[ "$installed_mode" == "750" ]] || ret=1
        fi
    fi

    metrics_stop

    return $ret
}

TEST_PORT_ALREADY_USED()'''
    if source.count(original) != 1:
        raise ValueError("Contrôle d'upgrade amont non reconnu ; aucun test supprimé.")
    mail_check = base64.b64encode(Path(__file__).with_name('native_mail_check.py').read_bytes()).decode()
    mail_guard = '''    if [ "$ret" -eq 0 ]; then
        local mail_result
        mail_result=$(LXC_EXEC "python3 -c 'import base64;exec(base64.b64decode(\\\"MAIL_CHECK_BASE64\\\"))' '$app_id'") || ret=1
        if [ "$ret" -eq 0 ]; then
            jq --argjson proof "$mail_result" '. + {arc_mail_transport:$proof}' "$current_test_results" > "$current_test_results.mail" || ret=1
            if [ "$ret" -eq 0 ]; then mv "$current_test_results.mail" "$current_test_results"; fi
        fi
    fi

'''.replace('MAIL_CHECK_BASE64', mail_check)
    checked = checked.replace('    metrics_stop\n', mail_guard + '    metrics_stop\n')
    tests.write_text(source.replace(original, checked))


if __name__ == "__main__":
    prepare(sys.argv[1])
