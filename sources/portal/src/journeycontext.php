<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
namespace Vigilance;

final class JourneyDenied extends \DomainException {}
final class JourneyInput extends \InvalidArgumentException {}

final class JourneyContext
{
    public function __construct(public readonly Database $db, public readonly Access $access) {}

    public function requireAny(array $roles): void
    {
        foreach ($roles as $role) if ($this->access->has($role)) return;
        throw new JourneyDenied('Action non autorisée pour ce profil.');
    }

    public static function text(array $input, string $key, int $limit = 180, bool $required = false): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) throw new JourneyInput('Champ invalide : '.$key);
        if ($required && trim($value) === '') throw new JourneyInput('Champ obligatoire : '.$key);
        return trim($value);
    }

    public function notify(string $title, string $body, array $roles = [], ?string $uid = null): void
    {
        $accounts = $this->db->rows('SELECT uid,roles FROM account WHERE company_id=? AND enabled=1',[$this->access->company]);
        foreach ($accounts as $account) {
            $matches = $uid !== null ? $account['uid'] === $uid : (bool)array_intersect($roles,json_decode($account['roles'],true,16,JSON_THROW_ON_ERROR));
            if (!$matches) continue;
            $this->db->execute('INSERT INTO journey_notification(company_id,uid,title,body,created_at) VALUES (?,?,?,?,?)',[$this->access->company,$account['uid'],$title,$body,gmdate('c')]);
        }
    }

    public static function validDate(string $value): bool
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date!==false && $date->format('Y-m-d')===$value;
    }

    public function notifications(): array
    {
        $this->access->account();
        return $this->db->rows('SELECT title,body,created_at FROM journey_notification WHERE company_id=? AND uid=? ORDER BY id DESC LIMIT 30',[$this->access->company,$this->access->uid]);
    }

    public function audit(string $action, string $target, int $revision): void
    {
        $this->db->audit($this->access->company,$this->access->uid,$action,$target,$revision);
    }
}
