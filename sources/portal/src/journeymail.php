<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
namespace Vigilance;

/** Transactional outbox. Only fixed, non-personal message templates leave the app. */
final class JourneyMail
{
    public const EVENTS = [
        'candidate.opened'=>['Votre dossier de candidature','Votre dossier vous attend dans votre profil Candidat.'],
        'candidate.submitted'=>['Dossier candidat soumis','Un dossier de candidature est prêt à être examiné.'],
        'offer.published'=>['Validation d’intérêt disponible','Une nouvelle version du FORM S 13 attend votre décision.'],
        'offer.refused'=>['FORM S 13 refusé ou à modifier','Une proposition requiert une reprise par la Direction.'],
        'offer.accepted'=>['Validation d’intérêt acceptée','Une proposition a été acceptée. L’accueil peut commencer.'],
        'welcome.completed'=>['Accueil entreprise terminé','Un accueil entreprise est terminé. Consultez le suivi des parcours.'],
    ];

    public function __construct(private readonly Database $db) {}

    public static function validAddress(string $value): bool
    {
        return strlen($value)<=180 && !preg_match('/[\x00-\x20\x7F]/',$value) && filter_var($value,FILTER_VALIDATE_EMAIL)!==false;
    }

    public static function publicUrl(string $value): string
    {
        $parts=parse_url($value);
        if (strlen($value)>2048 || !filter_var($value,FILTER_VALIDATE_URL) || !$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7F]/',$value)) {
            throw new JourneyInput('Adresse HTTPS de VIGIlance invalide.');
        }
        return rtrim($value,'/').'/';
    }

    public function status(Access $access): array
    {
        $access->require('admin');
        return $this->db->rows('SELECT m.state,m.last_error,COUNT(*) AS count FROM journey_mail m JOIN journey_notification n ON n.id=m.notification_id WHERE n.company_id=? GROUP BY m.state,m.last_error',[$access->company]);
    }

    public function retryFailed(Access $access): void
    {
        $this->db->transaction(function () use ($access): void {
            $access->require('admin');
            $this->db->execute("UPDATE journey_mail SET state='pending',attempts=0,available_at=0,last_error='' WHERE state='failed' AND notification_id IN (SELECT id FROM journey_notification WHERE company_id=?)",[$access->company]);
            $this->db->audit($access->company,$access->uid,'mail.retry_failed',(string)$access->company,1);
        });
    }

    /** All worker instances serialize on a private lock, never during a web request. */
    public function dispatch(array $config, ?callable $transport=null, ?int $now=null): array
    {
        if ($this->db->inTransaction()) throw new \LogicException('L’envoi e-mail doit rester hors transaction métier.');
        $counts=['sent'=>0,'retry'=>0,'cancelled'=>0,'failed'=>0,'waiting'=>0,'busy'=>false,'configured'=>false];
        $from=(string)($config['mail_from']??'');
        $url=(string)($config['public_url']??'');
        if (!self::validAddress($from) || $url==='') return $counts;
        $url=self::publicUrl($url);
        $counts['configured']=true;
        $lock=fopen($this->db->directory.'/.journey-mail.lock','c');
        if ($lock===false) throw new \RuntimeException('Verrou e-mail indisponible.');
        try {
            if (!flock($lock,LOCK_EX|LOCK_NB)) { $counts['busy']=true; return $counts; }
            $now??=time();
            $company=(int)($config['company_id']??1);
            $rows=$this->db->rows("SELECT m.*,n.company_id,n.uid FROM journey_mail m JOIN journey_notification n ON n.id=m.notification_id WHERE n.company_id=? AND m.state='pending' AND m.available_at<=? ORDER BY m.available_at,m.notification_id LIMIT 50",[$company,$now]);
            $transport??=[LocalMailTransport::class,'send'];
            foreach ($rows as $row) {
                $account=$this->db->rows('SELECT enabled,roles,notification_email FROM account WHERE company_id=? AND uid=?',[$company,$row['uid']])[0]??null;
                $required=json_decode($row['required_roles'],true,16,JSON_THROW_ON_ERROR);
                if (!$account || !(int)$account['enabled'] || ($required && !array_intersect($required,json_decode($account['roles'],true,16,JSON_THROW_ON_ERROR)))) {
                    $this->db->execute("UPDATE journey_mail SET state='cancelled',last_error='access_revoked' WHERE notification_id=?",[$row['notification_id']]);
                    $counts['cancelled']++; continue;
                }
                if (!self::validAddress($account['notification_email'])) {
                    $this->db->execute("UPDATE journey_mail SET last_error='recipient_missing',available_at=? WHERE notification_id=?",[$now+300,$row['notification_id']]);
                    $counts['waiting']++; continue;
                }
                [$title,$body]=self::EVENTS[$row['event']];
                $messageId=hash('sha256',$url.'|'.$company.'|'.$row['notification_id']).'@'.substr(strrchr($from,'@'),1);
                $accepted=false;
                try { $accepted=$transport($from,$account['notification_email'],'VIGIlance — '.$title,$body."\n\nOuvrir VIGIlance (connexion SSO requise) :\n".$url,$messageId)===true; }
                catch (\Throwable $error) { /* Never persist transport output or recipient details. */ }
                if ($accepted) {
                    $this->db->execute("UPDATE journey_mail SET state='sent',attempts=attempts+1,last_error='',sent_at=? WHERE notification_id=?",[gmdate('c',$now),$row['notification_id']]);
                    $counts['sent']++;
                } else {
                    $attempt=(int)$row['attempts']+1;
                    $state=$attempt>=8?'failed':'pending';
                    $this->db->execute('UPDATE journey_mail SET state=?,attempts=?,available_at=?,last_error=? WHERE notification_id=?',[$state,$attempt,$now+min(3600,60*(2**min($attempt-1,6))),'transport_failed',$row['notification_id']]);
                    $counts[$state==='failed'?'failed':'retry']++;
                }
            }
            return $counts;
        } finally { fclose($lock); }
    }
}

/** Uses YunoHost's local MTA; no password, shell interpolation or external library. */
final class LocalMailTransport
{
    public static function send(string $from, string $to, string $subject, string $body, string $messageId, string $executable='/usr/sbin/sendmail'): bool
    {
        if (!JourneyMail::validAddress($from) || !JourneyMail::validAddress($to) || preg_match('/[\r\n]/',$subject) || !preg_match('/^[a-f0-9]{64}@[a-z0-9.-]+$/iD',$messageId)) return false;
        $headers=['From: '.$from,'To: '.$to,'Subject: '.mb_encode_mimeheader($subject,'UTF-8','B',"\r\n",9),
            'Date: '.gmdate('D, d M Y H:i:s').' +0000','Message-ID: <'.$messageId.'>',
            'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
        $process=proc_open([$executable,'-i','-f',$from,'--',$to],[0=>['pipe','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if (!is_resource($process)) return false;
        $message=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
        $written=fwrite($pipes[0],$message); fclose($pipes[0]);
        $deadline=microtime(true)+20;
        do {
            $status=proc_get_status($process);
            if (!$status['running']) { $exit=$status['exitcode']; proc_close($process); return $written===strlen($message) && $exit===0; }
            usleep(100000);
        } while (microtime(true)<$deadline);
        proc_terminate($process,9); proc_close($process);
        return false;
    }
}
