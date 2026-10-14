<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class Identity
{
    public static function validate(string $uid): string
    {
        if (!preg_match('/^[a-zA-Z0-9._@-]{1,100}$/D', $uid)) throw new \DomainException('Identité non authentifiée.');
        return $uid;
    }

    public static function fromServer(array $server, array $config): string
    {
        if (($config['trusted_sso'] ?? false) !== true) throw new \DomainException('Authentification non configurée.');
        // Only the FastCGI parameter written by the trusted YunoHost proxy is accepted.
        // HTTP_REMOTE_USER and HTTP_YNH_USER may originate from an untrusted client.
        return self::validate((string) ($server['REMOTE_USER'] ?? ''));
    }

    public static function observe(Database $db, int $company, string $uid): void
    {
        self::validate($uid);
        // A verified login records an identity, not an authorization or a person.
        $db->execute('INSERT OR IGNORE INTO account(company_id,uid) VALUES (?,?)', [$company, $uid]);
    }
}
