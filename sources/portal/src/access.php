<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class Access
{
    public const ROLES = ['employee', 'rh', 'manager', 'direction', 'admin', 'qsse', 'crp', 'tutor', 'candidate', 'rp'];

    public function __construct(private Database $db, public int $company, public string $uid, public ?string $profile = null)
    {
        Identity::validate($uid);
        if ($company < 1) throw new \DomainException('Entreprise non autorisée.');
    }

    public function account(): array
    {
        $rows = $this->db->rows('SELECT * FROM account WHERE company_id=? AND uid=? AND enabled=1', [$this->company, $this->uid]);
        if (!$rows) throw new \DomainException('Votre accès doit être autorisé par un administrateur.');
        $row = $rows[0];
        $row['roles'] = json_decode($row['roles'], true, 8, JSON_THROW_ON_ERROR);
        $row['teams'] = json_decode($row['teams'], true, 8, JSON_THROW_ON_ERROR);
        if ($this->profile !== null && !in_array($this->profile, $row['roles'], true)) throw new \DomainException('Profil non autorisé.');
        return $row;
    }

    public function has(string $role): bool
    {
        $account = $this->account();
        return in_array($role, $account['roles'], true) && ($this->profile === null || $this->profile === $role);
    }

    public function require(string $role): void
    {
        if (!$this->has($role)) throw new \DomainException('Action non autorisée.');
    }

    public function personScope(bool $directory = false): array
    {
        $account = $this->account();
        if ($this->has('rh')) return ['company_id=?', [$this->company], 'rh'];
        if ($this->has('manager')) {
            $teams = $account['teams'];
            if (!$teams) return ['company_id=? AND 0=1', [$this->company], 'professional'];
            return ['company_id=? AND team IN ('.implode(',', array_fill(0, count($teams), '?')).')', array_merge([$this->company], $teams), 'professional'];
        }
        if ($directory && (int) $account['directory'] === 1) return ['company_id=? AND status<>?', [$this->company, 'archived'], 'professional'];
        if (!$directory && $this->has('employee')) return ['company_id=? AND account_uid=?', [$this->company, $this->uid], 'self'];
        throw new \DomainException('Dossier non autorisé.');
    }
}
