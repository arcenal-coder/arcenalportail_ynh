<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class Config
{
    public static function load(string $path): array
    {
        if (!is_file($path)) throw new \RuntimeException('Configuration absente.');
        $config = require $path;
        if (!is_array($config)) throw new \RuntimeException('Configuration invalide.');
        // Bridge owns config.php and may replace it. Application storage/roles must survive.
        $application = dirname($path).'/application-settings.php';
        if (is_file($application)) {
            $settings = require $application;
            if (!is_array($settings) || array_diff(array_keys($settings),['database_path','company_id','company_name','initial_admin','qsse_enabled','branding_origin'])) throw new \RuntimeException('Configuration applicative invalide.');
            $config = array_replace($config,$settings);
        }
        return $config;
    }
}
