<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

final class Database
{
    public \PDO $pdo;
    public string $directory;

    public function __construct(string $path)
    {
        if ($path === '' || $path[0] !== '/' || !is_dir(dirname($path))) {
            throw new \RuntimeException('Stockage privé non configuré.');
        }
        umask(0077);
        $this->directory = dirname($path);
        $this->pdo = new \PDO('sqlite:'.$path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=DELETE; PRAGMA synchronous=FULL;');
        chmod($path, 0600);
    }

    public function rows(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function transaction(callable $operation)
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $operation();
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    public function migrate(): void
    {
        $sql = file_get_contents(__DIR__.'/../migrations/001-dossiers.sql');
        $hash = hash('sha256', $sql);
        $this->transaction(function () use ($sql, $hash): void {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER PRIMARY KEY, checksum TEXT NOT NULL)');
            $versions = $this->rows('SELECT * FROM schema_version ORDER BY version');
            if ($versions) {
                if (count($versions) !== 1 || (int) $versions[0]['version'] !== 1 || !hash_equals($hash, $versions[0]['checksum'])) {
                    throw new \RuntimeException('Version ou empreinte de schéma incompatible.');
                }
                return;
            }
            $this->pdo->exec($sql);
            $this->execute('INSERT INTO schema_version VALUES (1,?)', [$hash]);
        });
    }

    public function checkSchema(): void
    {
        $rows = $this->rows('SELECT * FROM schema_version ORDER BY version');
        if (count($rows) !== 1 || (int) $rows[0]['version'] !== 1 || !hash_equals(hash_file('sha256', __DIR__.'/../migrations/001-dossiers.sql'), $rows[0]['checksum'])) {
            throw new \RuntimeException('Schéma non compatible.');
        }
    }

    public function initialize(int $company, string $name, ?string $admin): void
    {
        if ($company < 1 || trim($name) === '' || mb_strlen($name) > 180) throw new \InvalidArgumentException('Entreprise invalide.');
        if ($admin !== null) Identity::validate($admin);
        $this->transaction(function () use ($company, $name, $admin): void {
            $this->execute('INSERT OR IGNORE INTO company(id,name) VALUES (?,?)', [$company, trim($name)]);
            // An upgrade never elevates an account or changes an existing administrator.
            if ($admin !== null && !$this->rows('SELECT uid FROM account WHERE company_id=?', [$company])) {
                $this->execute('INSERT INTO account(company_id,uid,enabled,roles) VALUES (?,?,1,?)', [$company, $admin, '["admin"]']);
                $this->audit($company, 'installation', 'account.bootstrap', $admin, 1);
            }
        });
    }

    public function audit(int $company, string $actor, string $action, string $target, int $revision): void
    {
        $this->execute('INSERT INTO audit(company_id,actor,action,target,revision,created_at) VALUES (?,?,?,?,?,?)', [$company, $actor, $action, $target, $revision, gmdate('c')]);
    }

    public function bootstrap(int $company, string $admin): void
    {
        Identity::validate($admin);
        $this->transaction(function () use ($company,$admin): void {
            $accounts = $this->rows('SELECT roles FROM account WHERE company_id=? AND enabled=1', [$company]);
            if (array_filter($accounts,static fn($row) => in_array('admin',json_decode($row['roles'],true),true))) throw new \DomainException('Un administrateur existe déjà.');
            $this->execute('INSERT OR IGNORE INTO account(company_id,uid) VALUES (?,?)', [$company,$admin]);
            $this->execute('UPDATE account SET enabled=1,roles=?,revision=revision+1 WHERE company_id=? AND uid=?', ['["admin"]',$company,$admin]);
            $this->audit($company,'installation','account.bootstrap',$admin,(int)$this->rows('SELECT revision FROM account WHERE company_id=? AND uid=?',[$company,$admin])[0]['revision']);
        });
    }

    public function snapshot(string $destination, bool $replace = false): void
    {
        if ($destination === '' || $destination[0] !== '/' || !is_dir(dirname($destination)) || (!$replace && file_exists($destination))) throw new \InvalidArgumentException('Destination de sauvegarde invalide.');
        $temporary = $destination.'.'.bin2hex(random_bytes(8));
        try {
            $this->pdo->exec('VACUUM INTO '.$this->pdo->quote($temporary));
            chmod($temporary,0600);
            if (!rename($temporary,$destination)) throw new \RuntimeException('Sauvegarde impossible.');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }
}
