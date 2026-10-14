<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class AdminService
{
    public function __construct(private Database $db, private Access $access) {}

    public function company(): array
    {
        $this->access->account();
        return $this->db->rows('SELECT * FROM company WHERE id=?', [$this->access->company])[0];
    }

    public function rename(string $name, int $revision): void
    {
        $this->db->transaction(function () use ($name,$revision): void {
            $this->access->require('admin');
            if (trim($name) === '' || mb_strlen($name) > 180 || preg_match('/[\x00-\x1F\x7F]/', $name)) throw new \InvalidArgumentException('Nom d’entreprise invalide.');
            if ($this->db->execute('UPDATE company SET name=?,revision=revision+1 WHERE id=? AND revision=?', [trim($name),$this->access->company,$revision]) !== 1) throw new \DomainException('La configuration a changé. Rechargez-la.');
            $this->db->audit($this->access->company,$this->access->uid,'company.update',(string)$this->access->company,$revision+1);
        });
    }

    public function accounts(): array
    {
        $this->access->require('admin');
        return $this->db->rows('SELECT * FROM account WHERE company_id=? ORDER BY uid', [$this->access->company]);
    }

    public function setLogo(string $content, int $revision): void
    {
        $this->access->require('admin');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $extensions = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if (strlen($content) > 2097152 || !isset($extensions[$mime]) || !getimagesizefromstring($content)) throw new \InvalidArgumentException('Logo PNG, JPEG ou WebP de 2 Mo maximum requis.');
        $folder = $this->db->directory.'/logos';
        if (!is_dir($folder) && !mkdir($folder,0700)) throw new \RuntimeException('Stockage logo indisponible.');
        $name = hash('sha256',$content).'.'.$extensions[$mime];
        $this->db->transaction(function () use ($content,$revision,$folder,$name): void {
            $this->access->require('admin');
            if ((int)$this->company()['revision'] !== $revision) throw new \DomainException('La configuration a changé. Rechargez-la.');
            if (!is_file($folder.'/'.$name) && file_put_contents($folder.'/'.$name,$content,LOCK_EX) !== strlen($content)) throw new \RuntimeException('Enregistrement du logo impossible.');
            chmod($folder.'/'.$name,0600);
            $this->db->execute('UPDATE company SET logo=?,revision=revision+1 WHERE id=? AND revision=?',[$name,$this->access->company,$revision]);
            $this->db->audit($this->access->company,$this->access->uid,'company.logo',(string)$this->access->company,$revision+1);
        });
    }

    public function accountChoices(): array
    {
        $this->access->require('rh');
        return $this->db->rows('SELECT uid,roles FROM account WHERE company_id=? AND enabled=1 ORDER BY uid', [$this->access->company]);
    }

    public function updateAccount(string $uid, int $revision, array $roles, array $teams, bool $enabled, bool $directory): void
    {
        $this->db->transaction(function () use ($uid,$revision,$roles,$teams,$enabled,$directory): void {
            $this->access->require('admin');
            Identity::validate($uid);
            foreach ($roles as $role) if (!is_string($role)) throw new \InvalidArgumentException('Rôles invalides.');
            if (!array_is_list($roles) || array_diff($roles, Access::ROLES) || count($roles) !== count(array_unique($roles))) throw new \InvalidArgumentException('Rôles invalides.');
            if (count($teams) > 50 || !array_is_list($teams)) throw new \InvalidArgumentException('Périmètre invalide.');
            foreach ($teams as $team) if (!is_string($team) || trim($team) === '' || trim($team) !== $team || mb_strlen($team) > 180 || preg_match('/[\x00-\x1F\x7F]/', $team)) throw new \InvalidArgumentException('Équipe invalide.');
            $before = $this->db->rows('SELECT * FROM account WHERE company_id=? AND uid=?', [$this->access->company,$uid])[0] ?? null;
            if (!$before || $revision < 1 || (int)$before['revision'] !== $revision) throw new \DomainException('Compte non vérifié ou modifié. Rechargez-le.');
            // Do not revoke the last administrator, including by removing the admin role.
            if ((int)$before['enabled'] === 1 && in_array('admin',json_decode($before['roles'],true),true) && (!$enabled || !in_array('admin',$roles,true))) {
                $others = $this->db->rows('SELECT roles FROM account WHERE company_id=? AND enabled=1 AND uid<>?', [$this->access->company,$uid]);
                if (!array_filter($others, static fn($row) => in_array('admin',json_decode($row['roles'],true),true))) throw new \DomainException('Conservez au moins un administrateur actif.');
            }
            $this->db->execute('UPDATE account SET roles=?,teams=?,enabled=?,directory=?,revision=revision+1 WHERE company_id=? AND uid=? AND revision=?', [json_encode($roles,JSON_THROW_ON_ERROR),json_encode(array_values(array_unique($teams)),JSON_THROW_ON_ERROR),(int)$enabled,(int)$directory,$this->access->company,$uid,$revision]);
            $this->db->audit($this->access->company,$this->access->uid,'account.update',$uid,$revision+1);
        });
    }

    public function indicators(): array
    {
        $this->access->require('direction');
        return $this->db->rows('SELECT status,COUNT(*) AS count FROM person WHERE company_id=? GROUP BY status ORDER BY status', [$this->access->company]);
    }
}
