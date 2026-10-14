<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once __DIR__.'/load.php';

function vigilanceBootstrap(): array
{
    if (is_file(__DIR__.'/../.maintenance')) throw new RuntimeException('Maintenance en cours.');
    $path = getenv('ARCENAL_PORTAL_CONFIG') ?: '/etc/arcenal-qsse-portal/config.php';
    $config = \Vigilance\Config::load($path);
    $uid = \Vigilance\Identity::fromServer($_SERVER, $config);
    $db = new \Vigilance\Database((string)($config['database_path'] ?? ''));
    $db->checkSchema();
    $company = (int)($config['company_id'] ?? 1);
    \Vigilance\Identity::observe($db, $company, $uid);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict']);
    session_start();
    if (($_SESSION['uid'] ?? '') !== $uid || ($_SESSION['company'] ?? null) !== $company) {
        session_regenerate_id(true);
        $_SESSION = ['uid'=>$uid,'company'=>$company,'csrf'=>bin2hex(random_bytes(32))];
    }
    $base = new \Vigilance\Access($db,$company,$uid);
    $account = $base->account();
    $profile = $_SESSION['profile'] ?? ($account['roles'][0] ?? null);
    // Role revocations also invalidate a profile kept in an existing session.
    if ($profile !== null && !in_array($profile,$account['roles'],true)) {
        unset($_SESSION['profile']);
        $profile = $account['roles'][0] ?? null;
    }
    $access = new \Vigilance\Access($db,$company,$uid,$profile);
    $access->account();
    $qsse = ($config['qsse_enabled'] ?? true) && ($config['paired'] ?? true) && filter_var($config['gateway_url'] ?? '',FILTER_VALIDATE_URL) && parse_url($config['gateway_url'],PHP_URL_SCHEME) === 'https' && preg_match('/^[a-fA-F0-9]{64}$/D',$config['gateway_key'] ?? '');
    return ['config'=>$config,'uid'=>$uid,'db'=>$db,'access'=>$access,'account'=>$account,'profile'=>$profile,'qsse'=>(bool)$qsse];
}

function vigilanceError(Throwable $error): void
{
    $known = $error instanceof DomainException || $error instanceof InvalidArgumentException;
    http_response_code($error instanceof DomainException ? 403 : ($known ? 422 : 503));
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    if (!$known) error_log('VIGIlance '.get_class($error));
    $message = $known ? $error->getMessage() : 'Le service est momentanément indisponible. Contactez votre administrateur.';
    print '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VIGIlance</title><link rel="stylesheet" href="vigilance.css"><main class="content"><h1>VIGIlance</h1><p role="alert">'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><a href="?view=home">Revenir à l’accueil</a><footer>Propulsé par ARCenal OS</footer></main></html>';
}
