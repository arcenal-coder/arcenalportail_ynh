<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Run only by the installer, never through the public web root.
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/../src/load.php';
try {
    $path = getenv('ARCENAL_PORTAL_CONFIG');
    $directory = getenv('VIGILANCE_DATA_DIR');
    if (!$path || !$directory || !is_dir(dirname($path)) || !is_dir($directory)) throw new RuntimeException('Chemins non configurés.');
    $existing = is_file($path);
    $config = $existing ? \Vigilance\Config::load($path) : [];
    if (!is_array($config)) throw new RuntimeException('Configuration historique invalide.');
    $config['database_path'] = $config['database_path'] ?? $directory.'/vigilance.sqlite';
    $config['company_id'] = $config['company_id'] ?? 1;
    $config['company_name'] = $config['company_name'] ?? (getenv('VIGILANCE_COMPANY') ?: 'Entreprise');
    $config['initial_admin'] = $config['initial_admin'] ?? (getenv('VIGILANCE_INITIAL_ADMIN') ?: null);
    $brandingOrigin = getenv('VIGILANCE_BRANDING_ORIGIN');
    if ($brandingOrigin !== false && $brandingOrigin !== '') {
        $normalized = \Vigilance\Branding::normalizeOrigin($brandingOrigin);
        if ($normalized === '') throw new RuntimeException('Origine de marque invalide.');
        $config['branding_origin'] = $normalized;
    }
    $settings = array_intersect_key($config,array_flip(['database_path','company_id','company_name','initial_admin','qsse_enabled','branding_origin']));
    umask(0077);
    $write = static function(string $destination,array $values): void {
        $temporary = $destination.'.'.bin2hex(random_bytes(8));
        if (file_put_contents($temporary,"<?php\n// Configuration privée VIGIlance.\nreturn ".var_export($values,true).";\n") === false || !rename($temporary,$destination)) throw new RuntimeException('Configuration non enregistrée.');
        chmod($destination,0400);
    };
    $write(dirname($path).'/application-settings.php',$settings);
    // Preserve Bridge configuration byte-for-byte on upgrades.
    if (!$existing) $write($path,['trusted_sso'=>true,'paired'=>false,'gateway_url'=>'','gateway_key'=>'']);
} catch (Throwable $error) {
    fwrite(STDERR,"VIGIlance : configuration refusée.\n");
    exit(1);
}
