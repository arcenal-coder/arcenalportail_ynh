<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
namespace Vigilance;

final class WelcomeService
{
    public const STEPS = ['Bienvenue','Vidéo d’accueil','REX / Risques','Quiz de contrôle','Charte véhicule','Charte informatique','Droit à l’image','Charte d’intervention','Attestation','Communication','Merci'];
    public function __construct(private readonly JourneyContext $context, private readonly ContentService $contents) {}

    public function progress(): array
    {
        $this->context->requireAny(['employee']);
        $access = $this->context->access;
        $progress = $this->context->db->rows('SELECT * FROM welcome_progress WHERE company_id=? AND uid=?',[$access->company,$access->uid])[0] ?? ['step'=>0,'completed_at'=>null,'attestation'=>'{}'];
        if (!$progress['completed_at']) $progress['step'] = $this->earliestStep((int)$progress['step']);
        return $progress;
    }

    private function earliestStep(int $step): int
    {
        $current = $this->contents->current();
        foreach ([1=>'video',2=>'rex',3=>'quiz',4=>'vehicle',5=>'it',6=>'image',7=>'intervention',9=>'communication'] as $index=>$code) {
            if ($index>=$step) break;
            if (!isset($current[$code]) || !$this->hasProof((int)$current[$code]['id'])) return $index;
        }
        return $step;
    }

    public function blocked(): bool
    {
        return !$this->progress()['completed_at'] || (bool)$this->renewals();
    }

    public function renewals(): array
    {
        $this->context->requireAny(['employee']);
        return $this->context->db->rows('SELECT c.* FROM welcome_obligation o JOIN welcome_content c ON c.id=o.content_id WHERE o.company_id=? AND o.uid=? AND o.fulfilled_at IS NULL ORDER BY CASE c.code WHEN \'video\' THEN 0 WHEN \'quiz\' THEN 1 ELSE 2 END,c.id DESC',[$this->context->access->company,$this->context->access->uid]);
    }

    public function task(): ?array
    {
        $progress = $this->progress();
        if ($progress['completed_at']) return $this->renewals()[0] ?? null;
        $code = [1=>'video',2=>'rex',3=>'quiz',4=>'vehicle',5=>'it',6=>'image',7=>'intervention',9=>'communication'][(int)$progress['step']] ?? null;
        return $code===null ? null : ($this->contents->current()[$code] ?? null);
    }

    private function ensureProgress(): void
    {
        $this->context->requireAny(['employee']);
        $this->context->db->execute('INSERT OR IGNORE INTO welcome_progress(company_id,uid) VALUES (?,?)',[$this->context->access->company,$this->context->access->uid]);
    }

    public function advance(int $expected, array $input = []): void
    {
        $this->context->db->transaction(function () use ($expected,$input): void {
            $this->ensureProgress();
            $progress = $this->progress();
            if ($progress['completed_at'] || (int)$progress['step']!==$expected || $expected<0 || $expected>10) throw new JourneyDenied('Le parcours a changé. Rechargez la page.');
            $this->validateStep($expected,$input);
            if ($expected===10) { $this->finish(); return; }
            $this->context->db->execute('UPDATE welcome_progress SET step=? WHERE company_id=? AND uid=?',[$expected+1,$this->context->access->company,$this->context->access->uid]);
            $this->context->audit('welcome.step',$this->context->access->uid,$expected+1);
        });
    }

    private function validateStep(int $step, array $input): void
    {
        if (in_array($step,[0,10],true)) return;
        if ($step===8) { $this->attest($input); return; }
        $task = $this->task();
        if (!$task) throw new JourneyInput('Ce contenu doit être publié par l’Administration avant de continuer.');
        if (in_array($step,[1,3],true)) {
            if (!$this->hasProof((int)$task['id'])) throw new JourneyDenied('Terminez cette étape avant de continuer.');
            return;
        }
        if (($input['ack'] ?? '')!=='yes') throw new JourneyInput('Confirmez la prise de connaissance.');
        $this->proof($task,null);
    }

    private function attest(array $input): void
    {
        $date = JourneyContext::text($input,'date',10,true);
        $city = JourneyContext::text($input,'city',180,true);
        $phrase = JourneyContext::text($input,'phrase',40,true);
        $signature = JourneyContext::text($input,'signature',150000,true);
        if ($date!==gmdate('Y-m-d') || mb_strtolower($phrase)!=='lu et approuvé') throw new JourneyInput('La date du jour et la mention « lu et approuvé » sont requises.');
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/D',$signature,$match)) throw new JourneyInput('Signature manuscrite requise.');
        $bytes = base64_decode($match[1],true);
        if ($bytes===false || !getimagesizefromstring($bytes) || strlen($bytes)>100000) throw new JourneyInput('Signature invalide.');
        $proofs = $this->context->db->rows('SELECT c.code,c.revision,p.created_at FROM welcome_proof p JOIN welcome_content c ON c.id=p.content_id WHERE p.company_id=? AND p.uid=?',[$this->context->access->company,$this->context->access->uid]);
        $record = ['date'=>$date,'city'=>$city,'phrase'=>$phrase,'uid'=>$this->context->access->uid,'signature'=>$signature,'contents'=>$proofs,'recorded_at'=>gmdate('c')];
        $this->context->db->execute('UPDATE welcome_progress SET attestation=? WHERE company_id=? AND uid=?',[json_encode($record,JSON_THROW_ON_ERROR),$this->context->access->company,$this->context->access->uid]);
    }

    private function finish(): void
    {
        $current = $this->contents->current();
        foreach (array_merge(['video','quiz','rex','communication'],ContentService::PDF) as $code) {
            if (!isset($current[$code]) || !$this->hasProof((int)$current[$code]['id'])) throw new JourneyDenied('Une version a changé ou le parcours est incomplet. Reprenez les étapes concernées.');
        }
        if ($this->progress()['attestation']==='{}') throw new JourneyDenied('L’attestation est requise.');
        $access = $this->context->access;
        $this->context->db->execute('UPDATE welcome_progress SET completed_at=? WHERE company_id=? AND uid=?',[gmdate('c'),$access->company,$access->uid]);
        $this->context->db->execute("UPDATE person SET status='active',revision=revision+1,updated_at=? WHERE company_id=? AND account_uid=? AND status='onboarding'",[gmdate('c'),$access->company,$access->uid]);
        $this->context->notify('Accueil entreprise terminé','Un équipier a terminé son accueil. Consultez le suivi des parcours.',['admin','rh'],event:'welcome.completed');
        $this->context->audit('welcome.complete',$access->uid,1);
    }

    public function tick(int $id, float $position): bool
    {
        return $this->context->db->transaction(function () use ($id,$position): bool {
            $this->ensureProgress();
            $task = $this->task();
            if (!$task || $task['code']!=='video' || (int)$task['id']!==$id || !is_finite($position) || $position<0) throw new JourneyDenied('Vidéo non attendue dans le parcours.');
            $duration = (float)json_decode($task['payload'],true,16,JSON_THROW_ON_ERROR)['duration'];
            $access = $this->context->access;
            $now = microtime(true);
            $row = $this->context->db->rows('SELECT * FROM welcome_video WHERE company_id=? AND uid=? AND content_id=?',[$access->company,$access->uid,$id])[0] ?? null;
            $seconds = $row ? (float)$row['seconds'] : 0.0;
            $elapsed = $row ? max(0.0,min(8.0,$now-(float)$row['tick_at'])) : 0.0;
            if ($position<=$seconds+$elapsed+1.5 && $position>=$seconds-1.5) $seconds = min((float)$duration,max($seconds,min($position,$seconds+$elapsed)));
            $this->context->db->execute('INSERT INTO welcome_video(company_id,uid,content_id,seconds,tick_at) VALUES (?,?,?,?,?) ON CONFLICT(company_id,uid,content_id) DO UPDATE SET seconds=excluded.seconds,tick_at=excluded.tick_at',[$access->company,$access->uid,$id,$seconds,$now]);
            if ($seconds>=$duration-0.25) { $this->proof($task,null); return true; }
            return false;
        });
    }

    public function quiz(int $id, array $answers): int
    {
        return $this->context->db->transaction(function () use ($id,$answers): int {
            $this->ensureProgress();
            $task = $this->task();
            if (!$task || $task['code']!=='quiz' || (int)$task['id']!==$id) throw new JourneyDenied('Quiz non attendu dans le parcours.');
            $definition = json_decode($task['payload'],true,32,JSON_THROW_ON_ERROR);
            $score = QuizDefinition::score($definition,$answers);
            if ($score>=26) $this->proof($task,$score);
            $this->context->audit('welcome.quiz',$this->context->access->uid,(int)$task['revision']);
            return $score;
        });
    }

    public function renew(int $id, array $input): void
    {
        $this->context->db->transaction(function () use ($id,$input): void {
            $this->ensureProgress();
            $task = $this->task();
            if (!$this->progress()['completed_at'] || !$task || (int)$task['id']!==$id) throw new JourneyDenied('Actualisation non attendue.');
            if (in_array($task['code'],['video','quiz'],true)) {
                if (!$this->hasProof($id,true)) throw new JourneyDenied('Terminez la démarche avant de confirmer.');
            } else {
                if (($input['ack'] ?? '')!=='yes') throw new JourneyInput('Confirmez la prise de connaissance.');
                $this->proof($task,null);
            }
            $this->fulfill($task);
            $this->context->notify('Démarche d’accueil actualisée','Une actualisation a été terminée. Consultez le suivi d’accueil.',['admin','rh']);
            $this->context->audit('welcome.renew',$this->context->access->uid,(int)$task['revision']);
        });
    }

    private function fulfill(array $task): void
    {
        $this->context->db->execute('UPDATE welcome_obligation SET fulfilled_at=? WHERE company_id=? AND uid=? AND content_id IN (SELECT id FROM welcome_content WHERE company_id=? AND code=? AND revision<=?)',[gmdate('c'),$this->context->access->company,$this->context->access->uid,$this->context->access->company,$task['code'],(int)$task['revision']]);
    }

    public function hasProof(int $id, bool $renewal = false): bool
    {
        $access = $this->context->access;
        $after = $renewal ? (int)($this->context->db->rows('SELECT MAX(proof_after_id) AS n FROM welcome_obligation WHERE company_id=? AND uid=? AND content_id=? AND fulfilled_at IS NULL',[$access->company,$access->uid,$id])[0]['n']??0) : 0;
        return (bool)$this->context->db->rows('SELECT id FROM welcome_proof WHERE company_id=? AND uid=? AND content_id=? AND id>? LIMIT 1',[$access->company,$access->uid,$id,$after]);
    }

    private function proof(array $task, ?int $score): void
    {
        $this->context->db->execute('INSERT INTO welcome_proof(company_id,uid,content_id,score,created_at) VALUES (?,?,?,?,?)',[$this->context->access->company,$this->context->access->uid,(int)$task['id'],$score,gmdate('c')]);
    }

    public function followup(): array
    {
        $this->context->requireAny(['admin','rh','qsse']);
        return $this->context->db->rows("SELECT a.uid,p.first_name,p.last_name,p.team,COALESCE(w.step,0) AS step,w.completed_at,(SELECT COUNT(*) FROM welcome_obligation o WHERE o.company_id=a.company_id AND o.uid=a.uid AND o.fulfilled_at IS NULL) AS pending FROM account a LEFT JOIN person p ON p.company_id=a.company_id AND p.account_uid=a.uid LEFT JOIN welcome_progress w ON w.company_id=a.company_id AND w.uid=a.uid WHERE a.company_id=? AND a.enabled=1 AND EXISTS (SELECT 1 FROM json_each(a.roles) WHERE value='employee') ORDER BY a.uid",[$this->context->access->company]);
    }
}
