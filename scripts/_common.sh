#!/bin/bash

arcenal_normalize_gateway_url() {
    # A URL copied from a formatted document can arrive as Markdown:
    # [https://host/path](https://host/path). YunoHost expects a plain URL.
    printf '%s' "$1" | sed -E 's#^\[[^]]+\]\((https://[^)]*)\)$#\1#'
}

arcenal_configure_gateway_permission() {
    # The gateway belongs to Dolibarr, not to ARCenal Portail. Resolve the
    # Dolibarr YunoHost application from the configured URL, then create a
    # narrow, signed-machine-to-machine permission on that application.
    # This deliberately never changes the Dolibarr main permission.
    local result
    gateway_url=$(arcenal_normalize_gateway_url "$gateway_url")

    result=$(ARCENAL_GATEWAY_URL="$gateway_url" yunohost tools shell -c '
import json
import os
from urllib.parse import urlparse
from yunohost.app import _installed_apps, app_setting
from yunohost.permission import permission_create, permission_url, user_permission_list

url = urlparse(os.environ["ARCENAL_GATEWAY_URL"])
if url.scheme != "https" or not url.hostname or not url.path.endswith("/custom/arcenalqsse/gateway.php"):
    raise SystemExit("invalid gateway URL")

target = None
for app in _installed_apps():
    # ARCenal QSSE supports the official Dolibarr YunoHost package only.
    if not app.startswith("dolibarr"):
        continue
    domain = app_setting(app, "domain")
    base_path = app_setting(app, "path")
    if domain != url.hostname or not isinstance(base_path, str):
        continue
    base_path = "/" + base_path.strip("/")
    if base_path == "/":
        relative = url.path
    elif url.path.startswith(base_path + "/"):
        relative = url.path[len(base_path):]
    else:
        continue
    if target is None or len(base_path) > len(target[1]):
        target = (app, base_path, relative)

if target is None:
    raise SystemExit("no matching Dolibarr YunoHost application")

app, _, relative = target
permission = app + ".arcenalqsse_gateway"
permissions = user_permission_list(full=True, apps=[app])["permissions"]
if permission in permissions:
    permission_url(permission, url=relative, auth_header=False)
else:
    permission_create(permission, allowed=["visitors"], url=relative, auth_header=False, show_tile=False, protected=True)
print(json.dumps({"permission": permission, "path": relative}))
' 2>&1) || {
        ynh_print_warn "QSSE gateway permission was not changed: $result"
        return 0
    }

    ynh_print_info "QSSE gateway permission configured: $result"
}
