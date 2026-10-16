#!/bin/bash

vigilance_configure() {
    local php_runtime="${php_version:-${phpversion:-8.2}}"
    local branding_domain
    # The app subdomain may have no logo; use YunoHost's configured main domain.
    branding_domain=$(yunohost domain main-domain --output-as json | php"$php_runtime" -r '
        $data=json_decode(stream_get_contents(STDIN),true);
        $domain=is_array($data)?($data["current_main_domain"]??""):"";
        if(!is_string($domain)||!preg_match("/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD",$domain))exit(1);
        echo $domain;
    ') || ynh_die "Impossible de déterminer le domaine principal YunoHost."
    chmod 700 "$data_dir"
    chown -R "$app:$app" "$data_dir"
    ARCENAL_PORTAL_CONFIG="$install_dir/config.php" \
        VIGILANCE_DATA_DIR="$data_dir" VIGILANCE_COMPANY="${company_name:-Entreprise}" \
        VIGILANCE_INITIAL_ADMIN="${initial_admin:-}" \
        VIGILANCE_BRANDING_ORIGIN="https://$branding_domain" \
        php"$php_runtime" "$install_dir/bin/configure.php"
    chown "$app:$app" "$install_dir/config.php"
    chown "$app:$app" "$install_dir/application-settings.php"
    # Migrations are performed before opening the new application, never on web requests.
    ynh_exec_as_app env ARCENAL_PORTAL_CONFIG="$install_dir/config.php" \
        php"$php_runtime" "$install_dir/bin/manage.php" migrate
}
