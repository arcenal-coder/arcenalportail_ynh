<?php
// Outside web root; readable only by the PHP service account. Never commit a real key.
return [
 'trusted_sso' => false, // Set true ONLY after verifying proxy strips spoofed headers and requires SSO.
 'database_path' => '/home/yunohost.app/arcenalportail/vigilance.sqlite',
 'company_id' => 1,
 'company_name' => 'Entreprise',
 'branding_origin' => 'https://main-domain.example.invalid', // Main YunoHost domain, set automatically by the installer.
 'initial_admin' => null, // Explicit administrator chosen during installation; never first-login elevation.
 'mail_from' => '', // Installer: provisioned app@app-domain sender. Empty disables dispatch, not the outbox.
 'public_url' => '', // Installer: canonical HTTPS SSO entry point, with no query string or token.
 'qsse_enabled' => false,
 'gateway_url' => '', // Optional historical QSSE connection. Not used by SIRH.
 'gateway_key' => '', // 64 random hexadecimal characters shared with the module configuration.
];
