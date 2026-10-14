<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/../src/load.php';
if (PHP_SAPI !== 'cli') exit(1);
try {
    $configPath = getenv('ARCENAL_PORTAL_CONFIG');
    if (!$configPath || !is_file($configPath)) throw new RuntimeException('Configuration absente.');
    $config = \Vigilance\Config::load($configPath);
    $operation = $argv[1] ?? '';
    if ($operation === 'restore') {
        $source = $argv[2] ?? '';
        if (!is_file($source)) throw new RuntimeException('Snapshot absent.');
        $snapshot = new \Vigilance\Database($source);
        $snapshot->checkSchema();
        if ($snapshot->rows('PRAGMA integrity_check')[0]['integrity_check'] !== 'ok') throw new RuntimeException('Snapshot incohérent.');
        unset($snapshot);
        $destination = $config['database_path'];
        if (!is_dir(dirname($destination))) throw new RuntimeException('Stockage absent.');
        $temporary = $destination.'.restore.'.bin2hex(random_bytes(8));
        if (!copy($source,$temporary)) throw new RuntimeException('Restauration impossible.');
        chmod($temporary,0600);
        if (!rename($temporary,$destination)) throw new RuntimeException('Restauration impossible.');
    }
    $db = new \Vigilance\Database($config['database_path']);
    if ($operation === 'migrate') {
        $db->migrate();
        $db->initialize((int)($config['company_id'] ?? 1), (string)($config['company_name'] ?? 'Entreprise'), $config['initial_admin'] ?? null);
    } elseif ($operation === 'snapshot') {
        $db->checkSchema();
        $db->snapshot($argv[2] ?? '',true);
    } elseif ($operation === 'bootstrap') {
        $db->checkSchema();
        $db->bootstrap((int)($config['company_id'] ?? 1),(string)($argv[2] ?? ''));
    } elseif ($operation === 'check' || $operation === 'restore') {
        $db->checkSchema();
        if ($db->rows('PRAGMA integrity_check')[0]['integrity_check'] !== 'ok') throw new RuntimeException('Stockage incohérent.');
    } else throw new InvalidArgumentException('Commande inconnue.');
    fwrite(STDOUT, "VIGIlance : opération réussie.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'VIGIlance : opération refusée ('.get_class($error).").\n");
    exit(1);
}
