#!/bin/bash
# Sourced only inside the pinned package_check TEST_UPGRADE after mode assertion.
# Use package_check's existing LXC_EXEC backend; no second container framework.
s105_preupgrade_failure() {
    local primary="$1" collected=0
    mkdir -p "$S105_EVIDENCE" || return 1
    s105_collect || collected=$?
    python3 - "$S105_EVIDENCE" "$primary" "$collected" <<'PY'
import json,sys,time
from pathlib import Path
path=Path(sys.argv[1])/('preupgrade-failure-'+str(time.time_ns())+'.json')
path.write_text(json.dumps({'phase':'preupgrade','returncode':int(sys.argv[2]),'collection_returncode':int(sys.argv[3])})+'\n')
PY
}

s105_send() {
    local source="$1" target="$2" piece
    LXC_EXEC "python3 -c 'from pathlib import Path; Path(\"$target.b64\").write_text(\"\")'" || return 1
    while IFS= read -r piece; do
        LXC_EXEC "python3 -c 'from pathlib import Path; p=Path(\"$target.b64\"); p.open(\"a\").write(\"$piece\")'" || return 1
    done < <(python3 -c 'import base64,sys; s=base64.b64encode(open(sys.argv[1],"rb").read()).decode(); print("\n".join(s[i:i+24000] for i in range(0,len(s),24000)))' "$source")
    LXC_EXEC "python3 -c 'import base64;from pathlib import Path;p=Path(\"$target\");p.write_bytes(base64.b64decode(Path(\"$target.b64\").read_text()))'" || return 1
}

s105_after_upgrade() {
    # Absence is normal for all twelve historical scenarios.
    local ready
    ready=$(LXC_EXEC "python3 -c 'from pathlib import Path;print(int(Path(\"/var/lib/vigilance-s105/pre-ready\").is_file()))'") || return 1
    ready=${ready//$'\r'/}
    if [ "$ready" = 0 ]; then return 0; fi
    [ "$ready" = 1 ] || return 1
    local result=0
    s105_send "$S105_INPUTS/fault.zip" /var/lib/vigilance-s105/fault.zip || result=1
    s105_send "$S105_INPUTS/expected-code.json" /var/lib/vigilance-s105/expected-code.json || result=1
    s105_send "$S105_INPUTS/inputs.json" /var/lib/vigilance-s105/inputs.json || result=1
    if [ "$result" -eq 0 ]; then
        LXC_EXEC 'env S105_NATIVE_RUNNER=1 python3 /var/lib/vigilance-s105/guest.py post arcenalportail' || result=1
    fi
    s105_collect || result=1
    return "$result"
}

s105_collect() {
    local present
    present=$(LXC_EXEC "python3 -c 'from pathlib import Path;print(int(Path(\"/var/lib/vigilance-s105/evidence\").is_dir()))'") || return 1
    present=${present//$'\r'/}
    if [ "$present" = 0 ]; then return 0; fi
    [ "$present" = 1 ] || return 1
    local result=0
    mkdir -p "$S105_EVIDENCE"
    # Collect even on failure, without copying credentials, config files or databases.
    if ! LXC_EXEC "python3 -c 'import base64,io,tarfile; b=io.BytesIO(); t=tarfile.open(fileobj=b,mode=\"w:gz\"); t.add(\"/var/lib/vigilance-s105/evidence\",arcname=\"evidence\");t.close();print(base64.b64encode(b.getvalue()).decode())'" > "$S105_EVIDENCE/guest-${WORKER_ID:-0}.b64"; then
        result=1
    fi
    python3 -c 'import base64,sys;from pathlib import Path;p=Path(sys.argv[1]);p.with_suffix(".tar.gz").write_bytes(base64.b64decode(p.read_text(),validate=False))' "$S105_EVIDENCE/guest-${WORKER_ID:-0}.b64" || result=1
    return "$result"
}
