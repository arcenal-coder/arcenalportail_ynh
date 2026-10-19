<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../src/load.php';
try {
    if (is_file(__DIR__.'/../.maintenance')) exit(0);
    $config=\Vigilance\Config::load(getenv('ARCENAL_PORTAL_CONFIG')?:'/etc/arcenal-qsse-portal/config.php');
    $db=new \Vigilance\Database((string)($config['database_path']??''));
    $db->checkSchema();
    $result=(new \Vigilance\JourneyMail($db))->dispatch($config);
    // Counts only: no identities, addresses, messages or transport errors in logs.
    fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR)."\n");
    exit($result['configured']?0:1);
} catch (Throwable $error) {
    fwrite(STDERR,"VIGIlance : traitement e-mail indisponible.\n");
    exit(1);
}
