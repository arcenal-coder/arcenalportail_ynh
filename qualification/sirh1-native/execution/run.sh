#!/bin/bash
# FUTURE GitHub disposable runner ONLY. Never invoke from the desktop session.
set +x
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_EVENT_NAME:-}" = workflow_dispatch
test "${GITHUB_REPOSITORY:-}" = arcenal-coder/arcenalportail_ynh
test "${GITHUB_REF:-}" = refs/heads/qualification/sirh1-native-ynh3
test "${RUNNER_ENVIRONMENT:-}" = github-hosted
test -n "${RUNNER_TEMP:-}"
Q="$GITHUB_WORKSPACE/qualification/sirh1-native"
E="$Q/execution"
WORK="$RUNNER_TEMP/s105"
CHECKER="$RUNNER_TEMP/package_check"
test ! -e "$WORK"
test ! -e "$CHECKER"
mkdir "$WORK"
export S105_INPUTS="$Q/prepared"
export S105_BRIDGE="$E/bridge.sh"
export S105_EVIDENCE="$WORK/evidence"
mkdir "$S105_EVIDENCE"
python3 "$E/prepare.py" stage --inputs "$S105_INPUTS" --history "$GITHUB_WORKSPACE" --output "$WORK/package" > "$WORK/stage.json"
python3 "$E/prepare.py" copy-tools --source "$WORK/package/tools" --output "$WORK/tools"

# The same official checker/revision and dependencies as the existing workflow.
git clone https://github.com/YunoHost/package_check "$CHECKER"
git -C "$CHECKER" checkout f7d32cadac3cfced282decede024707fba9e6ec6
test "$(git -C "$CHECKER" rev-parse HEAD)" = f7d32cadac3cfced282decede024707fba9e6ec6
python3 -m venv --system-site-packages "$WORK/env"
"$WORK/env/bin/pip" install imgkit
python3 "$WORK/tools/prepare_package_check.py" "$CHECKER"
python3 "$E/prepare.py" patch-checker "$CHECKER"
python3 "$CHECKER/lib/parse_tests_toml.py" "$WORK/package" > "$WORK/package-plan.json"
# Keep the exact modified CHECKER scripts with the raw reports for review.
cp "$CHECKER/lib/tests.sh" "$WORK/tests.sh.used"
cp "$WORK/tools/native_mail_check.py" "$WORK/native_mail_check.py.used"
status=0
set +e
(
    cd "$CHECKER"
    sudo env PATH="$WORK/env/bin:$PATH" YNHDEV_BACKEND=incus DIST=bookworm YNH_BRANCH=stable \
        S105_INPUTS="$S105_INPUTS" S105_BRIDGE="$S105_BRIDGE" S105_EVIDENCE="$S105_EVIDENCE" \
        S105_SANITIZER="$E/sanitize.py" \
        "$CHECKER/package_check.sh" "$WORK/package"
) 2>&1 | python3 "$E/sanitize.py" stream | tee "$WORK/checker.log"
checker_status=("${PIPESTATUS[@]}")
set -e
status=${checker_status[0]}
if [ "${checker_status[1]}" -ne 0 ] || [ "${checker_status[2]}" -ne 0 ]; then status=125; fi
sudo chown -R "$USER:$USER" "$CHECKER" "$WORK"
python3 "$CHECKER/package_linter/package_linter.py" "$WORK/package" --json > "$WORK/linter.json" || status=1
commit=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["checker_control_commit"])' "$WORK/stage.json")
python3 "$WORK/tools/verify_package_report.py" "$CHECKER" --linter-report "$WORK/linter.json" \
    --expected-commit "$commit" --qualification-output "$WORK/package-qualification.json" || status=1
python3 "$E/collect.py" --work "$WORK" --inputs "$S105_INPUTS" || status=1
exit "$status"
