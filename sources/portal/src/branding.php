<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace Vigilance;

/** Optional, bounded public YunoHost branding. Never handles user credentials. */
final class Branding
{
    private const TTL = 300;
    private const MAX_LOGO = 2097152;
    private const TYPES = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    private string $origin;
    private \Closure $transport;
    private \Closure $clock;

    public function __construct(private string $directory, string $origin, ?callable $transport = null, ?callable $clock = null)
    {
        $this->origin = self::normalizeOrigin($origin);
        $this->transport = $transport ? \Closure::fromCallable($transport) : self::http(...);
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn(): int => time();
    }

    public static function normalizeOrigin(string $value): string
    {
        $parts = parse_url($value);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443) || !in_array($parts['path'] ?? '', ['', '/'], true)
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/iD', $parts['host'])
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) return '';
        return 'https://'.strtolower($parts['host']);
    }

    private function emptyState(): array
    {
        return ['origin'=>$this->origin,'checked_at'=>0,'logo_file'=>'','mime'=>'','remote_file'=>'','error'=>false];
    }

    private function cached(): array
    {
        $data = json_decode(@file_get_contents($this->directory.'/state.json') ?: '', true);
        if (!is_array($data) || ($data['origin'] ?? null) !== $this->origin) return $this->emptyState();
        $state = array_intersect_key($data, $this->emptyState()) + $this->emptyState();
        if (!is_int($state['checked_at']) || !is_string($state['logo_file']) || !is_string($state['mime'])
            || !is_string($state['remote_file']) || !is_bool($state['error'])) return $this->emptyState();
        if ($state['logo_file'] !== '' && (!preg_match('/^[a-f0-9]{64}\.(png|jpg|webp)$/D', $state['logo_file']) || !isset(self::TYPES[$state['mime']]))) return $this->emptyState();
        return $state;
    }

    private function status(array $state): array
    {
        $state['enabled'] = $this->origin !== '';
        $state['available'] = $this->readImage($state) !== null;
        return $state;
    }

    private function failedRefresh(array $state): array
    {
        $state['error'] = true;
        return $this->status($state);
    }

    public function resolve(bool $force = false): array
    {
        $state = $this->cached();
        if ($this->origin === '') return $this->status($this->emptyState());
        $now = ($this->clock)();
        if (!$force && $state['checked_at'] > 0 && $now >= $state['checked_at'] && $now - $state['checked_at'] < self::TTL) return $this->status($state);
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true)) return $this->failedRefresh($state);
        $lock = @fopen($this->directory.'/sync.lock', 'c');
        if (!$lock) return $this->failedRefresh($state);
        @chmod($this->directory.'/sync.lock',0600);
        if (!flock($lock,LOCK_EX|LOCK_NB)) { fclose($lock); return $this->failedRefresh($state); }
        try {
            $state = $this->cached();
            if (!$force && $state['checked_at'] > 0 && $now >= $state['checked_at'] && $now - $state['checked_at'] < self::TTL) return $this->status($state);
            try {
                $metadata = ($this->transport)($this->origin.'/yunohost/portalapi/public',65536);
                if (!is_string($metadata) || strlen($metadata) > 65536) throw new \RuntimeException('Métadonnées invalides.');
                $data = json_decode($metadata,true,16,JSON_THROW_ON_ERROR);
                if (!is_array($data) || !array_key_exists('portal_logo',$data)) throw new \RuntimeException('Métadonnées invalides.');
                $file = $data['portal_logo'];
                if ($file === '' || $file === null) {
                    $state = $this->emptyState(); // An explicitly removed source logo is no longer displayed.
                } else {
                    if (!is_string($file) || !preg_match('/^[a-z0-9_-]{1,160}\.(png|jpe?g|webp)$/iD',$file)) throw new \RuntimeException('Référence logo invalide.');
                    if ($force || $state['remote_file'] !== $file || !$this->status($state)['available']) {
                        $body = ($this->transport)($this->origin.'/yunohost/sso/customassets/'.$file,self::MAX_LOGO);
                        $mime = self::imageType($body);
                        if ($mime === null) throw new \RuntimeException('Logo invalide.');
                        $name = hash('sha256',$body).'.'.self::TYPES[$mime];
                        if (!$this->write($this->directory.'/'.$name,$body)) throw new \RuntimeException('Cache indisponible.');
                        $state['logo_file'] = $name; $state['mime'] = $mime; $state['remote_file'] = $file;
                    }
                }
                $state['error'] = false;
            } catch (\Throwable $error) {
                // The last validated logo remains usable; never expose upstream messages or bodies.
                $state['error'] = true;
            }
            $state['checked_at'] = $now;
            if (!$this->write($this->directory.'/state.json',json_encode($state,JSON_THROW_ON_ERROR))) return $this->failedRefresh($state);
            return $this->status($state);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    public function image(): ?array
    {
        return $this->readImage($this->resolve());
    }

    private function readImage(array $state): ?array
    {
        if ($state['logo_file'] === '') return null;
        $body = @file_get_contents($this->directory.'/'.$state['logo_file'],false,null,0,self::MAX_LOGO+1);
        if (!is_string($body) || self::imageType($body) !== $state['mime'] || !str_starts_with($state['logo_file'],hash('sha256',$body).'.')) return null;
        return ['body'=>$body,'mime'=>$state['mime']];
    }

    private static function imageType(mixed $body): ?string
    {
        if (!is_string($body) || $body === '' || strlen($body) > self::MAX_LOGO) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $size = @getimagesizefromstring($body);
        return isset(self::TYPES[$mime]) && $size && $size[0] > 0 && $size[1] > 0 && $size[0] <= 4096 && $size[1] <= 4096 ? $mime : null;
    }

    private function write(string $path, string $content): bool
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        try {
            if (@file_put_contents($temporary,$content,LOCK_EX) !== strlen($content)) return false;
            @chmod($temporary,0600);
            return @rename($temporary,$path);
        } finally { if (is_file($temporary)) @unlink($temporary); }
    }

    private static function http(string $url, int $limit): string
    {
        $body = '';
        $handle = curl_init($url);
        if (!$handle) throw new \RuntimeException('Source indisponible.');
        curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_TIMEOUT_MS=>2000,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'VIGIlance branding',
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body,$limit): int {
                if (strlen($body)+strlen($chunk)>$limit) return 0;
                $body.=$chunk; return strlen($chunk);
            }]);
        $ok = curl_exec($handle);
        if ($ok === false || curl_getinfo($handle,CURLINFO_HTTP_CODE) !== 200) throw new \RuntimeException('Source indisponible.');
        return $body;
    }
}
