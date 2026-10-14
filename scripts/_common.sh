#!/bin/bash

vigilance_configure() {
    local php_runtime="${php_version:-${phpversion:-8.2}}"
    chmod 700 "$data_dir"
    chown -R "$app:$app" "$data_dir"
    ARCENAL_PORTAL_CONFIG="$install_dir/config.php" \
        VIGILANCE_DATA_DIR="$data_dir" VIGILANCE_COMPANY="${company_name:-Entreprise}" \
        VIGILANCE_INITIAL_ADMIN="${initial_admin:-}" \
        php"$php_runtime" "$install_dir/bin/configure.php"
    chown "$app:$app" "$install_dir/config.php"
    chown "$app:$app" "$install_dir/application-settings.php"
    # Migrations are performed before opening the new application, never on web requests.
    ynh_exec_as_app env ARCENAL_PORTAL_CONFIG="$install_dir/config.php" \
        php"$php_runtime" "$install_dir/bin/manage.php" migrate
}
