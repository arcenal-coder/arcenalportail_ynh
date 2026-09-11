#!/bin/bash

arcenal_normalize_gateway_url() {
    # A URL copied from a formatted document can arrive as Markdown:
    # [https://host/path](https://host/path). YunoHost expects a plain URL.
    printf '%s' "$1" | sed -E 's#^\[[^]]+\]\((https://[^)]*)\)$#\1#'
}

arcenal_configure_gateway_permission() {
    # Keep the repair here as well: some legacy releases source this helper
    # without passing through the normal configuration path first.
    gateway_url=$(arcenal_normalize_gateway_url "$gateway_url")

    if ynh_permission_exists --permission="gateway"; then
        ynh_permission_url --permission="gateway" --url="$gateway_url" --auth_header=false
    else
        ynh_permission_create \
            --permission="gateway" \
            --url="$gateway_url" \
            --allowed="visitors" \
            --show_tile=false \
            --auth_header=false \
            --protected=true
    fi
}
