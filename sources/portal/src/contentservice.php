<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
namespace Vigilance;

final class ContentService
{
    public const CODES = ['video'=>'Vidéo d’accueil entreprise','quiz'=>'Quiz d’accueil entreprise','vehicle'=>'Charte d’utilisation véhicule','it'=>'Charte informatique','image'=>'Autorisation de droit à l’image','intervention'=>'Charte d’intervention','rex'=>'REX / Accidentologie','communication'=>'Communication'];
    public const PDF = ['vehicle','it','image','intervention'];
    public const VIDEO_MAX_BYTES = 1073741824;
    public function __construct(private readonly JourneyContext $context) {}

    public function current(): array
    {
        $this->context->access->account();
        $rows = $this->context->db->rows('SELECT c.* FROM welcome_content c WHERE company_id=? AND revision=(SELECT MAX(n.revision) FROM welcome_content n WHERE n.company_id=c.company_id AND n.code=c.code) ORDER BY c.code',[$this->context->access->company]);
        return array_column($rows,null,'code');
    }

    public function get(int $id): array
    {
        $this->context->access->account();
        $row = $this->context->db->rows('SELECT * FROM welcome_content WHERE company_id=? AND id=?',[$this->context->access->company,$id])[0] ?? null;
        if (!$row) throw new JourneyDenied('Contenu non accessible.');
        return $row;
    }

    public function publish(string $code, int $expected, array $payload, string $repeat, string $asset = '', string $mime = ''): int
    {
        return $this->context->db->transaction(function () use ($code,$expected,$payload,$repeat,$asset,$mime): int {
            $this->context->requireAny(['admin']);
            if (!isset(self::CODES[$code]) || !in_array($repeat,['yes','no'],true)) throw new JourneyInput('Contenu et choix de reprise explicite requis.');
            $values = $this->validatePayload($code,$payload,$asset);
            if ((int)($this->current()[$code]['revision'] ?? 0) !== $expected) throw new JourneyDenied('Le contenu a changé. Rechargez la page.');
            $db = $this->context->db;
            $db->execute('INSERT INTO welcome_content(company_id,code,revision,title,payload,asset,mime,repeat_required,published_at) VALUES (?,?,?,?,?,?,?,?,?)',[$this->context->access->company,$code,$expected+1,self::CODES[$code],json_encode($values,JSON_THROW_ON_ERROR),$asset,$mime,(int)($repeat==='yes'),gmdate('c')]);
            $id = (int)$db->pdo->lastInsertId();
            if ($repeat === 'yes') $this->requireRenewal($id,$code);
            $this->context->audit('content.publish',$code,$expected+1);
            return $id;
        });
    }

    private function validatePayload(string $code, array $payload, string $asset): array
    {
        if ($code === 'quiz') return QuizDefinition::validate($payload);
        if (in_array($code,array_merge(['video'],self::PDF),true)) {
            $pattern=$code==='video'?'/^[a-f0-9]{64}\.(mp4|webm)$/D':'/^[a-f0-9]{64}\.pdf$/D';
            if (!preg_match($pattern,$asset) || !is_file($this->context->db->directory.'/welcome/'.$asset)) throw new JourneyInput('Importez un fichier valide avant publication.');
            if ($code !== 'video') return [];
            $duration = filter_var($payload['duration'] ?? null,FILTER_VALIDATE_FLOAT);
            if ($duration === false || !is_finite($duration) || $duration<1 || $duration>14400) throw new JourneyInput('Durée vidéo requise, entre 1 et 14 400 secondes.');
            return ['duration'=>$duration];
        }
        $text = JourneyContext::text($payload,'text',20000,true);
        $link = JourneyContext::text($payload,'link',2000);
        if ($link !== '' && (!filter_var($link,FILTER_VALIDATE_URL) || parse_url($link,PHP_URL_SCHEME)!=='https' || parse_url($link,PHP_URL_USER)!==null || parse_url($link,PHP_URL_PASS)!==null)) throw new JourneyInput('Lien HTTPS valide requis.');
        return ['text'=>$text,'link'=>$link];
    }

    private function requireRenewal(int $id, string $code): void
    {
        $company = $this->context->access->company;
        $this->context->db->execute('INSERT INTO welcome_obligation(company_id,uid,content_id,proof_after_id) SELECT w.company_id,w.uid,?,COALESCE((SELECT MAX(p.id) FROM welcome_proof p WHERE p.company_id=w.company_id AND p.uid=w.uid),0) FROM welcome_progress w WHERE w.company_id=? AND w.completed_at IS NOT NULL',[$id,$company]);
        if ($code === 'video') {
            $quiz = $this->current()['quiz'] ?? null;
            if ($quiz) $this->context->db->execute('INSERT INTO welcome_obligation(company_id,uid,content_id,proof_after_id) SELECT w.company_id,w.uid,?,COALESCE((SELECT MAX(p.id) FROM welcome_proof p WHERE p.company_id=w.company_id AND p.uid=w.uid),0) FROM welcome_progress w WHERE w.company_id=? AND w.completed_at IS NOT NULL ON CONFLICT(company_id,uid,content_id) DO UPDATE SET fulfilled_at=NULL,proof_after_id=excluded.proof_after_id',[(int)$quiz['id'],$company]);
        }
    }

    public function import(string $code, string $temporary, int $error): array
    {
        $this->context->requireAny(['admin']);
        if (in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) throw new JourneyInput('Le fichier dépasse la limite du serveur. Vidéo : 1 Go maximum ; PDF : 20 Mo maximum.');
        if ($error === UPLOAD_ERR_PARTIAL) throw new JourneyInput('Le transfert est incomplet. Réessayez l’import.');
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($temporary)) throw new JourneyInput('Sélectionnez un fichier à importer.');
        return $this->store($code,$temporary);
    }

    public function store(string $code, string $path): array
    {
        $this->context->requireAny(['admin']);
        if (!is_file($path) || !in_array($code,array_merge(['video','communication'],self::PDF),true)) throw new JourneyInput('Fichier non autorisé.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = $code==='video' ? ['video/mp4'=>'mp4','video/webm'=>'webm'] : ($code==='communication'?['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp']:['application/pdf'=>'pdf']);
        self::validateSize($code,(int)filesize($path));
        if (!isset($extensions[$mime])) throw new JourneyInput('Format non autorisé : vidéo MP4/WebM, document PDF ou QR PNG/JPEG/WebP requis.');
        if ($code==='communication' && (!getimagesize($path) || getimagesize($path)[0]>4096 || getimagesize($path)[1]>4096)) throw new JourneyInput('QR PNG, JPEG ou WebP valide requis.');
        $folder = $this->context->db->directory.'/welcome';
        if (!is_dir($folder) && !mkdir($folder,0700)) throw new \RuntimeException('Stockage privé indisponible.');
        $name = hash_file('sha256',$path).'.'.$extensions[$mime];
        $this->writeAsset($path,$folder,$name);
        return ['asset'=>$name,'mime'=>$mime];
    }

    public static function validateSize(string $code, int $size): void
    {
        $maximum = $code==='video' ? self::VIDEO_MAX_BYTES : ($code==='communication'?2097152:20971520);
        if ($size>0 && $size<=$maximum) return;
        $label = $code==='video' ? '1 Go' : ($code==='communication'?'2 Mo':'20 Mo');
        throw new JourneyInput('Fichier vide ou supérieur à '.$label.'.');
    }

    private function writeAsset(string $source, string $folder, string $name): void
    {
        $destination=$folder.'/'.$name;
        $hash=hash_file('sha256',$source);
        if (is_file($destination)) {
            if (!hash_equals($hash,hash_file('sha256',$destination))) throw new \RuntimeException('Le contenu déjà stocké a une empreinte incohérente.');
            return;
        }
        $temporary=$folder.'/.import-'.bin2hex(random_bytes(12));
        try {
            if (!copy($source,$temporary) || !hash_equals($hash,hash_file('sha256',$temporary))) throw new \RuntimeException('Import du contenu impossible.');
            chmod($temporary,0600);
            if (!rename($temporary,$destination)) throw new \RuntimeException('Publication du fichier impossible.');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    public function history(): array
    {
        $this->context->requireAny(['admin']);
        return $this->context->db->rows('SELECT code,revision,repeat_required,published_at FROM welcome_content WHERE company_id=? ORDER BY id DESC LIMIT 30',[$this->context->access->company]);
    }
}

final class QuizDefinition
{
    public static function validate(array $input): array
    {
        $questions = $input['questions'] ?? null;
        if (!is_array($questions) || !array_is_list($questions) || count($questions)<1 || count($questions)>60) throw new JourneyInput('Ajoutez entre 1 et 60 questions.');
        foreach ($questions as $question) if (!is_array($question)) throw new JourneyInput('Question invalide.');
        $values = array_map(self::question(...),$questions);
        if (array_sum(array_column($values,'points')) !== 30) throw new JourneyInput('Le total du barème doit être de 30 points.');
        return ['questions'=>$values,'threshold'=>26,'total'=>30];
    }

    private static function question(array $input): array
    {
        $text = JourneyContext::text($input,'text',2000,true);
        $choices = $input['choices'] ?? null;
        if (!is_array($choices) || !array_is_list($choices) || count($choices)<2 || count($choices)>10) throw new JourneyInput('Chaque question doit avoir entre 2 et 10 choix.');
        foreach ($choices as $choice) if (!is_string($choice)) throw new JourneyInput('Choix de réponse invalide.');
        $clean = array_map(static fn(string $choice): string => JourneyContext::text(['choice'=>$choice],'choice',1000,true),$choices);
        $correct = $input['correct'] ?? null;
        $points = filter_var($input['points'] ?? null,FILTER_VALIDATE_INT);
        if (!is_array($correct) || !array_is_list($correct) || !$correct || count(array_unique($correct))!==count($correct)) throw new JourneyInput('Sélectionnez les bonnes réponses.');
        foreach ($correct as $index) if (!is_int($index) || !array_key_exists($index,$clean)) throw new JourneyInput('Réponse correcte invalide.');
        if ($points===false || $points<1 || $points>30) throw new JourneyInput('Barème invalide.');
        return ['text'=>$text,'choices'=>$clean,'correct'=>$correct,'points'=>$points];
    }

    public static function score(array $definition, array $answers): int
    {
        if (count($answers)!==count($definition['questions']) || !array_is_list($answers)) throw new JourneyInput('Répondez à toutes les questions.');
        $score = 0;
        foreach ($definition['questions'] as $index => $question) {
            $chosen = $answers[$index];
            if (!is_array($chosen) || !$chosen || count(array_unique($chosen))!==count($chosen)) throw new JourneyInput('Réponse invalide.');
            foreach ($chosen as $choice) if (!is_int($choice) || !array_key_exists($choice,$question['choices'])) throw new JourneyInput('Choix inconnu.');
            $correct = $question['correct'];
            sort($chosen); sort($correct);
            if ($chosen===$correct) $score += $question['points'];
        }
        return $score;
    }
}
