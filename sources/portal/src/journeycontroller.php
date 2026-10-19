<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
require_once __DIR__.'/journeyicons.php';

function vigilanceJourney(array $context, string $view): bool
{
    $routes = ['home','candidates','candidate','offer','welcome','welcome-contents','followup','notifications','welcome-file','video-tick'];
    $journey = new \Vigilance\JourneyContext($context['db'],$context['access']);
    $contents = new \Vigilance\ContentService($journey);
    $welcome = new \Vigilance\WelcomeService($journey,$contents);
    vigilanceJourneyGate($context,$welcome,$view);
    if (!in_array($view,$routes,true)) return false;
    $candidates = new \Vigilance\CandidateService($journey);
    if ($view==='welcome-file') { vigilanceJourneyFile($contents,$journey); return true; }
    if ($view==='video-tick') { vigilanceJourneyTick($welcome); return true; }
    vigilanceJourneyPage($context,$view,$journey,$contents,$welcome,$candidates);
    return true;
}

function vigilanceJourneyGate(array $context, \Vigilance\WelcomeService $welcome, string $view): void
{
    if (!$context['access']->has('employee')) return;
    $candidate = $context['db']->rows('SELECT c.stage FROM candidature c JOIN person p ON p.id=c.person_id WHERE c.company_id=? AND p.account_uid=?',[$context['access']->company,$context['uid']])[0]??null;
    if ($candidate && $candidate['stage']!=='accepted') throw new \Vigilance\JourneyDenied('L’acceptation de votre FORM S 13 est nécessaire avant le passage Équipier.');
    if ($welcome->blocked() && !in_array($view,['home','welcome','logo','welcome-file','video-tick','notifications'],true) && ($_POST['action']??'')!=='profile') throw new \Vigilance\JourneyDenied('Terminez votre accueil entreprise pour accéder à votre espace Équipier.');
}

function vigilanceJourneyFile(\Vigilance\ContentService $contents, \Vigilance\JourneyContext $journey): void
{
    $journey->requireAny(['admin','employee']);
    $content = $contents->get((int)($_GET['id'] ?? 0));
    if (!preg_match('/^[a-f0-9]{64}\.(pdf|mp4|webm|png|jpg|webp)$/D',$content['asset'])) throw new \Vigilance\JourneyDenied('Fichier non disponible.');
    $path = $journey->db->directory.'/welcome/'.$content['asset'];
    if (!is_file($path)) throw new \Vigilance\JourneyDenied('Fichier non disponible.');
    $size = filesize($path);
    [$start,$end] = vigilanceByteRange((string)($_SERVER['HTTP_RANGE'] ?? ''),$size);
    header('Content-Type: '.$content['mime']);
    header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'self\'; sandbox');
    header('Content-Disposition: inline; filename="'.basename($content['asset']).'"');
    header('Accept-Ranges: bytes'); header('Content-Length: '.($end-$start+1));
    if ($start>0 || $end<$size-1) { http_response_code(206); header('Content-Range: bytes '.$start.'-'.$end.'/'.$size); }
    session_write_close();
    $stream = fopen($path,'rb');
    if ($stream===false) throw new \RuntimeException('Lecture du contenu impossible.');
    fseek($stream,$start); $remaining = $end-$start+1;
    while ($remaining>0 && !feof($stream)) { $chunk=fread($stream,min(65536,$remaining)); if ($chunk===false || $chunk==='') break; print $chunk; $remaining-=strlen($chunk); }
    fclose($stream);
}

function vigilanceByteRange(string $range, int $size): array
{
    if ($range==='') return [0,$size-1];
    if (!preg_match('/^bytes=(\d+)-(\d*)$/D',$range,$matches)) throw new \Vigilance\JourneyInput('Plage de lecture invalide.');
    $start=(int)$matches[1]; $end=$matches[2]===''?$size-1:(int)$matches[2];
    if ($start>$end || $end>=$size) throw new \Vigilance\JourneyInput('Plage de lecture hors fichier.');
    return [$start,$end];
}

function vigilanceJourneyCsrf(): void
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) throw new \Vigilance\JourneyDenied('Formulaire expiré.');
}

function vigilanceJourneyTick(\Vigilance\WelcomeService $welcome): void
{
    if ($_SERVER['REQUEST_METHOD']!=='POST') throw new \Vigilance\JourneyDenied('Méthode non autorisée.');
    vigilanceJourneyCsrf();
    $position = filter_var($_POST['position'] ?? null,FILTER_VALIDATE_FLOAT);
    if ($position===false) throw new \Vigilance\JourneyInput('Position vidéo invalide.');
    $done = $welcome->tick((int)($_POST['content_id'] ?? 0),(float)$position);
    header('Content-Type: application/json');
    print json_encode(['done'=>$done],JSON_THROW_ON_ERROR);
}

function vigilanceJourneyPage(array $context, string $view, \Vigilance\JourneyContext $journey, \Vigilance\ContentService $contents, \Vigilance\WelcomeService $welcome, \Vigilance\CandidateService $candidates): void
{
    extract($context,EXTR_SKIP);
    $error = ''; $score = null;
    $notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        vigilanceJourneyCsrf();
        try { $destination = vigilanceJourneyAction($context,$journey,$contents,$welcome,$candidates); }
        catch (DomainException | InvalidArgumentException $failure) { http_response_code($failure instanceof DomainException?403:422); $error=$failure->getMessage(); }
        if (isset($destination)) { $_SESSION['notice']=$_SESSION['quiz_result']??'Enregistrement effectué.'; unset($_SESSION['quiz_result']); header('Location: '.$destination,true,303); return; }
    }
    $admin = new \Vigilance\AdminService($db,$access);
    $company = $admin->company();
    $brandingStatus = (new \Vigilance\Branding($db->directory.'/branding',(string)($config['branding_origin']??'')))->resolve();
    $hasBrandLogo = $brandingStatus['available'] || $company['logo']!=='';
    $e = static fn(string|int|float|null $value): string => htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $csrf = '<input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'">';
    $labels = ['admin'=>'Administration','rh'=>'RH','employee'=>'Équipier','candidate'=>'Candidat','direction'=>'Direction','manager'=>'Manager','qsse'=>'QSSE','rp'=>'RP','crp'=>'CRP','tutor'=>'Tuteur'];
    require __DIR__.'/../views/journey.php';
}

function vigilanceJourneyAction(array $context, \Vigilance\JourneyContext $journey, \Vigilance\ContentService $contents, \Vigilance\WelcomeService $welcome, \Vigilance\CandidateService $candidates): string
{
    $action = \Vigilance\JourneyContext::text($_POST,'action',40,true);
    if ($action==='profile') return vigilanceJourneyProfile($context);
    if ($action==='candidate-create') return '?view=candidate&id='.rawurlencode($candidates->create(\Vigilance\JourneyContext::text($_POST,'uid'),\Vigilance\JourneyContext::text($_POST,'email')));
    if ($action==='candidate-save') { $candidates->save((string)($_POST['id']??''),(int)($_POST['revision']??0),vigilanceJourneyFields(\Vigilance\CandidateService::FIELDS),($_POST['submit']??'')==='yes'); return '?view=candidate'; }
    if ($action==='offer-publish') { $candidates->publishOffer((string)($_POST['id']??''),(int)($_POST['revision']??0),vigilanceJourneyFields(\Vigilance\CandidateService::TERMS)); return '?view=candidate&id='.rawurlencode((string)$_POST['id']); }
    if ($action==='offer-decide') return vigilanceJourneyDecide($candidates);
    if ($action==='content-publish') { vigilanceJourneyPublish($contents); return '?view=welcome-contents'; }
    if ($action==='welcome-next') { $welcome->advance((int)($_POST['step']??-1),$_POST); return '?view=welcome'; }
    if ($action==='welcome-renew') { $welcome->renew((int)($_POST['content_id']??0),$_POST); return '?view=welcome'; }
    if ($action==='quiz-submit') { vigilanceJourneyQuiz($welcome); return '?view=welcome'; }
    throw new \Vigilance\JourneyInput('Action inconnue.');
}

function vigilanceJourneyFields(array $fields): array
{
    return array_intersect_key($_POST,$fields);
}

function vigilanceJourneyProfile(array $context): string
{
    $selected = \Vigilance\JourneyContext::text($_POST,'profile',40,true);
    if (!in_array($selected,$context['access']->account()['roles'],true)) throw new \Vigilance\JourneyDenied('Profil non autorisé.');
    $_SESSION['profile']=$selected;
    return '?view=home';
}

function vigilanceJourneyDecide(\Vigilance\CandidateService $candidates): string
{
    $decision = \Vigilance\JourneyContext::text($_POST,'decision',20,true);
    if (!in_array($decision,['accept','refuse'],true)) throw new \Vigilance\JourneyInput('Décision requise.');
    if ($decision==='accept' && ($_POST['confirm']??'')!=='yes') throw new \Vigilance\JourneyInput('Confirmez personnellement votre acceptation.');
    $candidates->decide((string)($_POST['id']??''),(int)($_POST['offer_id']??0),$decision==='accept',\Vigilance\JourneyContext::text($_POST,'reason',2000));
    if ($decision==='accept') $_SESSION['profile']='employee';
    return '?view=home';
}

function vigilanceJourneyQuiz(\Vigilance\WelcomeService $welcome): void
{
    $answers = $_POST['answers']??[];
    if (!is_array($answers) || !array_is_list($answers)) throw new \Vigilance\JourneyInput('Réponses invalides.');
    $values = [];
    foreach ($answers as $answer) {
        if (!is_array($answer)) throw new \Vigilance\JourneyInput('Réponses invalides.');
        $chosen=[];
        foreach ($answer as $value) { if (!is_string($value) || !ctype_digit($value)) throw new \Vigilance\JourneyInput('Choix invalide.'); $chosen[]=(int)$value; }
        $values[]=$chosen;
    }
    $score=$welcome->quiz((int)($_POST['content_id']??0),$values);
    $_SESSION['quiz_result']='Résultat du quiz : '.$score.'/30. '.($score>=26?'Bravo, vous pouvez continuer.':'Vous pouvez recommencer autant de fois que nécessaire.');
}

function vigilanceJourneyPublish(\Vigilance\ContentService $contents): void
{
    $code=\Vigilance\JourneyContext::text($_POST,'code',30,true);
    $payload=[]; $asset=['asset'=>'','mime'=>''];
    if ($code==='quiz') {
        $raw = $_POST['questions']??[];
        if (!is_array($raw)) throw new \Vigilance\JourneyInput('Questionnaire invalide.');
        $payload=['questions'=>array_map('vigilanceJourneyQuestion',array_values($raw))];
    } elseif (in_array($code,array_merge(['video'],\Vigilance\ContentService::PDF),true)) {
        $upload=$_FILES['content']??[];
        $asset=$contents->import($code,(string)($upload['tmp_name']??''),(int)($upload['error']??-1));
        $payload=['duration'=>$_POST['duration']??''];
    } else $payload=['text'=>$_POST['text']??'','link'=>$_POST['link']??''];
    if ($code==='communication') {
        $previous=$contents->current()['communication']??null;
        if ($previous) $asset=['asset'=>$previous['asset'],'mime'=>$previous['mime']];
        if (($_FILES['qr']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $asset=$contents->import('communication',(string)($_FILES['qr']['tmp_name']??''),(int)($_FILES['qr']['error']??-1));
    }
    $contents->publish($code,(int)($_POST['revision']??0),$payload,\Vigilance\JourneyContext::text($_POST,'repeat',3),$asset['asset'],$asset['mime']);
}

function vigilanceJourneyQuestion(array $input): array
{
    $choices=\Vigilance\JourneyContext::text($input,'choices',10000,true);
    $correct=\Vigilance\JourneyContext::text($input,'correct',100,true);
    if (!preg_match('/^\d+(?:\s*,\s*\d+)*$/D',$correct)) throw new \Vigilance\JourneyInput('Indiquez les numéros des bonnes réponses séparés par des virgules.');
    return ['text'=>$input['text']??'','choices'=>array_map('trim',explode("\n",str_replace("\r",'',$choices))),'correct'=>array_map(static fn(string $index): int => (int)trim($index)-1,explode(',',$correct)),'points'=>$input['points']??''];
}
