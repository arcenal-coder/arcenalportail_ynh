<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class PersonService
{
    public const STATUSES = ['candidate', 'onboarding', 'active', 'leaving', 'archived'];
    public const FIELDS = ['first_name','last_name','job','team','manager_uid','email','account_uid','status','starts_on','ends_on'];
    private const PROFESSIONAL = 'id,first_name,last_name,job,team,manager_uid,email';

    public function __construct(private Database $db, private Access $access) {}

    public function listing(string $query = '', int $page = 1, bool $directory = false): array
    {
        [$where, $params, $visibility] = $this->access->personScope($directory);
        if (mb_strlen($query) > 100 || $page < 1 || $page > 10000) throw new \InvalidArgumentException('Recherche invalide.');
        $where .= ' AND (first_name LIKE ? ESCAPE \'!\' OR last_name LIKE ? ESCAPE \'!\' OR team LIKE ? ESCAPE \'!\')';
        $like = '%'.str_replace(['!','%','_'], ['!!','!%','!_'], trim($query)).'%';
        $params = array_merge($params, [$like,$like,$like]);
        $total = (int) $this->db->rows('SELECT COUNT(*) AS n FROM person WHERE '.$where, $params)[0]['n'];
        $columns = $visibility === 'professional' ? self::PROFESSIONAL : '*';
        $rows = $this->db->rows('SELECT '.$columns.' FROM person WHERE '.$where.' ORDER BY last_name,first_name,id LIMIT 25 OFFSET '.(($page - 1) * 25), $params);
        return ['people' => $rows, 'total' => $total, 'page' => $page];
    }

    public function get(string $id, bool $directory = false): array
    {
        [$where, $params, $visibility] = $this->access->personScope($directory);
        $columns = $visibility === 'professional' ? self::PROFESSIONAL : '*';
        $rows = $this->db->rows('SELECT '.$columns.' FROM person WHERE '.$where.' AND id=?', array_merge($params, [$id]));
        if (!$rows) throw new \DomainException('Dossier non accessible.');
        return $rows[0];
    }

    public function mine(): ?array
    {
        $this->access->require('employee');
        return $this->db->rows('SELECT * FROM person WHERE company_id=? AND account_uid=?', [$this->access->company, $this->access->uid])[0] ?? null;
    }

    public function create(array $input): string
    {
        return $this->db->transaction(function () use ($input): string {
            $this->access->require('rh');
            $values = $this->validate($input);
            $id = self::uuid();
            $fields = implode(',', self::FIELDS);
            $this->db->execute('INSERT INTO person(id,company_id,'.$fields.',created_at,updated_at) VALUES ('.implode(',', array_fill(0, 14, '?')).')', array_merge([$id,$this->access->company], array_values($values), [gmdate('c'),gmdate('c')]));
            $this->db->audit($this->access->company, $this->access->uid, 'person.create', $id, 1);
            return $id;
        });
    }

    public function update(string $id, int $revision, array $input): void
    {
        $this->db->transaction(function () use ($id, $revision, $input): void {
            $this->access->require('rh');
            $before = $this->get($id);
            if ($revision < 1 || (int) $before['revision'] !== $revision) throw new \DomainException('Le dossier a changé. Rechargez-le avant de modifier.');
            $values = $this->validate($input, $before);
            $set = implode(',', array_map(static fn($field) => $field.'=?', self::FIELDS));
            $changed = $this->db->execute('UPDATE person SET '.$set.',revision=revision+1,updated_at=? WHERE company_id=? AND id=? AND revision=?', array_merge(array_values($values), [gmdate('c'),$this->access->company,$id,$revision]));
            if ($changed !== 1) throw new \DomainException('Le dossier a changé. Rechargez-le.');
            $this->db->audit($this->access->company, $this->access->uid, $values['status'] === 'archived' && $before['status'] !== 'archived' ? 'person.archive' : 'person.update', $id, $revision + 1);
        });
    }

    private function validate(array $input, ?array $before = null): array
    {
        if (array_diff(array_keys($input), self::FIELDS)) throw new \InvalidArgumentException('Champ de dossier non autorisé.');
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || mb_strlen($value) > 180 || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new \InvalidArgumentException('Champ de dossier invalide.');
            $values[$field] = trim($value);
        }
        if ($values['first_name'] === '' || $values['last_name'] === '' || !in_array($values['status'], self::STATUSES, true)) throw new \InvalidArgumentException('Nom, prénom et situation sont obligatoires.');
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('E-mail professionnel invalide.');
        foreach (['starts_on','ends_on'] as $field) {
            $value = $values[$field];
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value)) throw new \InvalidArgumentException('Date invalide.');
        }
        if ($values['starts_on'] !== '' && $values['ends_on'] !== '' && $values['ends_on'] < $values['starts_on']) throw new \InvalidArgumentException('La sortie précède l’entrée.');
        foreach (['account_uid','manager_uid'] as $field) {
            if ($values[$field] !== '') {
                Identity::validate($values[$field]);
                // Revocation restricts access, not maintenance of an existing historical link.
                if ($before !== null && $values[$field] === $before[$field]) continue;
                $accounts = $this->db->rows('SELECT roles FROM account WHERE company_id=? AND uid=? AND enabled=1', [$this->access->company,$values[$field]]);
                if (!$accounts) throw new \InvalidArgumentException('Compte vérifié et autorisé requis.');
                if ($field === 'manager_uid' && !in_array('manager', json_decode($accounts[0]['roles'], true), true)) throw new \InvalidArgumentException('Responsable manager requis.');
            }
        }
        $values['account_uid'] = $values['account_uid'] === '' ? null : $values['account_uid'];
        if ($values['account_uid'] !== null && $this->db->rows('SELECT id FROM person WHERE company_id=? AND account_uid=? AND id<>?', [$this->access->company,$values['account_uid'],$before['id'] ?? ''])) throw new \DomainException('Ce compte est déjà associé à un dossier.');
        return $values;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
