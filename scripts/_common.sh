#!/bin/bash

arcenal_configure_gateway_permission() {
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
