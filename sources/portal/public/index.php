<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// This application must be deployed behind SSOwat, which authenticates the user.
$configPath=getenv('ARCENAL_PORTAL_CONFIG')?:'/etc/arcenal-qsse-portal/config.php';
if(!is_file($configPath)){http_response_code(503);exit('Portail non configuré.');}
$config=require $configPath;
if(!is_array($config)||empty($config['trusted_sso'])||!filter_var($config['gateway_url']??'',FILTER_VALIDATE_URL)||parse_url($config['gateway_url'],PHP_URL_SCHEME)!=='https'){http_response_code(503);exit('Configuration de sécurité incomplète.');}
$uid=$_SERVER['HTTP_REMOTE_USER']??$_SERVER['REMOTE_USER']??$_SERVER['HTTP_YNH_USER']??'';
if(!preg_match('/^[a-zA-Z0-9._@-]{1,100}$/D',$uid)){http_response_code(401);exit('Connectez-vous depuis ARCenal Système.');}
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict']);
session_start();
if(($_SESSION['uid']??'')!==$uid){session_regenerate_id(true);$_SESSION=['uid'=>$uid,'csrf'=>bin2hex(random_bytes(32))];}
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function esc($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function gateway(array $input):array{
    global $config,$uid;
    $input['uid']=$uid;
    $body=json_encode($input,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $ts=(string)time();$nonce=bin2hex(random_bytes(16));
    $sig=hash_hmac('sha256',$ts."\n".$nonce."\n".$body,$config['gateway_key']);
    $ch=curl_init($config['gateway_url']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Arcenal-Timestamp: '.$ts,'X-Arcenal-Nonce: '.$nonce,'X-Arcenal-Signature: '.$sig]]);
    $response=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if($response===false||$code!==200)throw new RuntimeException('La transmission n’a pas été confirmée. Conservez votre saisie et réessayez.');
    $data=json_decode($response,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new RuntimeException('Réponse indisponible.');
    return $data;
}
function initials(string $name):string{
    $parts=preg_split('/[._@-]+/',$name,-1,PREG_SPLIT_NO_EMPTY)?:[];
    return strtoupper(substr($parts[0]??'A',0,1).substr($parts[1]??'',0,1));
}
function displayName(string $name):string{
    $local=explode('@',$name)[0];
    return implode(' ',array_map(fn($p)=>ucfirst(strtolower($p)),preg_split('/[._-]+/',$local,-1,PREG_SPLIT_NO_EMPTY)?:[$local]));
}
$views=['home','mat','report','records','actions','documents','planning','habilitations','companionship','directory'];
$view=(string)($_GET['view']??'home');
if(!in_array($view,$views,true))$view='home';
$error='';$success='';$sent=false;$data=['identity'=>$uid,'records'=>[],'actions'=>[],'mats'=>[]];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Formulaire expiré.');}
    try{
        if(($_POST['form']??'report')==='action'){
            $res=gateway(['operation'=>'action.progress','request_id'=>(string)($_POST['request_id']??''),'action_id'=>(int)($_POST['action_id']??0),'version'=>(int)($_POST['version']??0),'progress'=>(int)($_POST['progress']??-1),'note'=>(string)($_POST['note']??'')]);
            $success='Avancement enregistré pour '.$res['reference'].'.';$view='actions';
        }elseif(($_POST['form']??'report')==='mat'){
            $input=[];foreach(['mat_feeling','mat_equipment','mat_documents','mat_conditions','mat_risks','observation'] as $field)$input[$field]=is_string($_POST[$field]??null)?$_POST[$field]:'';
            $res=gateway(['operation'=>'mat.submit','request_id'=>(string)($_SESSION['mat_request_id']??''),'record'=>$input]);
            $success=$res['alert']?'MAT enregistrée. Contactez votre responsable et ne débutez pas dans ces conditions.':'MAT enregistrée. Bonne journée et prenez soin de vous.';
            unset($_SESSION['mat_request_id']);$view='mat';
        }else{
            $input=[];foreach(['title','description','context_label','location','occurred_at','origin','immediate_action','category','severity','report_type','nc_type','analysis_immediate'] as $field)$input[$field]=is_string($_POST[$field]??null)?$_POST[$field]:'';
            if(!empty($_FILES['photo']['tmp_name'])){
                if((int)$_FILES['photo']['error']!==UPLOAD_ERR_OK||(int)$_FILES['photo']['size']>2097152)throw new RuntimeException('Photo invalide ou supérieure à 2 Mo.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$_FILES['photo']['tmp_name']);
                if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Format de photo non autorisé.');
                $input['photo']=['name'=>(string)$_FILES['photo']['name'],'content'=>base64_encode((string)file_get_contents($_FILES['photo']['tmp_name']))];
            }
            $res=gateway(['operation'=>'submit','request_id'=>(string)($_SESSION['request_id']??''),'record'=>$input]);
            $success='Remontée reçue : '.$res['reference'];$sent=true;unset($_SESSION['request_id']);$view='report';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
try{$data=gateway(['operation'=>'list']);}catch(Throwable $e){$error=$error?:'La liaison QSSE est momentanément indisponible. Vous pouvez consulter le portail, mais les données ne sont pas actualisées.';}
$_SESSION['request_id']=$_SESSION['request_id']??bin2hex(random_bytes(16));
$_SESSION['mat_request_id']=$_SESSION['mat_request_id']??bin2hex(random_bytes(16));
$name=displayName($uid);$firstName=explode(' ',$name)[0];
$states=['submitted'=>'Soumise','qualified'=>'Qualifiée','treatment'=>'En traitement','open'=>'Ouverte','in_progress'=>'En cours','closed'=>'Close','classified'=>'Classée avec motif'];
$openActions=count(array_filter($data['actions']??[],fn($a)=>in_array($a['status']??'',['open','in_progress'],true)));
$active=function(string $target)use($view):string{return $view===$target?' aria-current="page" class="active"':'';};
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#4f397c"><title>Mon espace — ARCenal</title><link rel="stylesheet" href="qsse.css"></head>
<body><div class="portal-shell">
<input class="nav-control" type="checkbox" id="nav-control" aria-hidden="true">
<label class="nav-overlay" for="nav-control" aria-label="Fermer le menu"></label>
<aside class="side-nav" aria-label="Navigation principale">
    <a class="brand" href="?view=home"><span class="brand-mark">A</span><span><strong>ARCenal</strong><small>Système</small></span></a>
    <nav>
        <p class="nav-label">Mon espace</p>
        <a href="?view=home"<?=$active('home')?>><span>⌂</span>Accueil équipier</a>
        <a href="?view=planning"<?=$active('planning')?>><span>▦</span>Mon planning <small>SIRH</small></a>
        <a href="?view=habilitations"<?=$active('habilitations')?>><span>✓</span>Habilitations <small>SIRH</small></a>
        <a href="?view=companionship"<?=$active('companionship')?>><span>♙</span>Compagnonnage <small>SIRH</small></a>
        <a href="?view=directory"<?=$active('directory')?>><span>☎</span>Annuaire société <small>SIRH</small></a>
        <p class="nav-label">QSSE</p>
        <a href="?view=mat"<?=$active('mat')?>><span>☀</span>Ma mise au travail</a>
        <a href="?view=report"<?=$active('report')?>><span>!</span>Remontée terrain</a>
        <a href="?view=records"<?=$active('records')?>><span>◎</span>Mes remontées</a>
        <a href="?view=actions"<?=$active('actions')?>><span>↗</span>Mes actions PAO<?php if($openActions):?><b><?=$openActions?></b><?php endif;?></a>
        <a href="?view=documents"<?=$active('documents')?>><span>▤</span>Documents / LDA</a>
    </nav>
    <div class="account"><span class="avatar"><?=esc(initials($uid))?></span><span><strong><?=esc($firstName)?></strong><small><?=esc($uid)?></small></span></div>
</aside>
<div class="portal-main">
<header class="topbar"><label class="menu-button" for="nav-control" aria-label="Ouvrir le menu"><i></i><i></i><i></i></label><a class="mobile-brand" href="?view=home"><span class="brand-mark">A</span><strong>ARCenal</strong></a><span class="top-identity"><?=esc($firstName)?></span></header>
<main class="content">
<?php if($error):?><p class="alert error" role="alert"><?=esc($error)?></p><?php endif;?>
<?php if($success):?><p class="alert success" role="status"><?=esc($success)?></p><?php endif;?>
<?php if($view==='home'):?>
<section class="welcome"><span class="eyebrow">MON ESPACE ÉQUIPIER</span><h1>Bonjour <?=esc($firstName)?>,</h1><p>Retrouvez les outils utiles à votre activité et contribuez simplement à l’amélioration continue.</p><div class="welcome-actions"><a class="button primary" href="?view=mat">Démarrer ma MAT</a><a class="button ghost" href="?view=report">Faire une remontée</a></div></section>
<section class="section-heading"><div><span class="eyebrow">ACCÈS RAPIDE</span><h2>Mes services</h2></div></section>
<div class="service-grid">
    <a class="service-card accent" href="?view=mat"><span class="service-icon">☀</span><span><strong>Mise au travail</strong><small>Vérifier mes conditions avant de commencer</small></span><i>›</i></a>
    <a class="service-card" href="?view=report"><span class="service-icon">!</span><span><strong>Remontée terrain</strong><small>Signaler, proposer, améliorer</small></span><i>›</i></a>
    <a class="service-card" href="?view=actions"><span class="service-icon">↗</span><span><strong>Mes actions PAO</strong><small><?=$openActions?> action<?=($openActions>1?'s':'')?> à piloter</small></span><i>›</i></a>
    <a class="service-card" href="?view=documents"><span class="service-icon">▤</span><span><strong>Documents applicables</strong><small>Politique, DUER, LDA et consignes</small></span><i>›</i></a>
</div>
<section class="today-card"><div><span class="eyebrow">AUJOURD’HUI</span><h2>Votre sécurité commence avant l’intervention</h2><p>Quelques secondes suffisent pour confirmer vos conditions de travail.</p></div><a class="round-link" href="?view=mat" aria-label="Ouvrir ma mise au travail">→</a></section>
<?php elseif($view==='mat'):?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">QSSE / AVANT INTERVENTION</span><h1>Ma mise au travail</h1><p>Confirmez que les conditions sont réunies avant de démarrer votre activité.</p></header>
<form action="?view=mat" method="post" class="panel form-stack"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="form" value="mat">
<div class="choice-block"><label>Comment vous sentez-vous aujourd’hui ?</label><div class="segmented"><label><input type="radio" name="mat_feeling" value="sun" checked><span>☀ Bien</span></label><label><input type="radio" name="mat_feeling" value="cloud"><span>☁ Moyen</span></label><label><input type="radio" name="mat_feeling" value="storm"><span>⚡ Mal</span></label></div></div>
<?php foreach(['mat_equipment'=>'J’ai mon matériel et mes EPI','mat_documents'=>'Je maîtrise le PDP, le mode opératoire et la coactivité','mat_conditions'=>'Les conditions d’exécution sont réunies','mat_risks'=>'Les principaux risques sont maîtrisés'] as $field=>$label):?><div class="check-row"><span class="check-number">✓</span><label><strong><?=esc($label)?></strong><select name="<?=esc($field)?>" required><option value="yes">Oui</option><option value="<?=($field==='mat_risks'?'alert':'no')?>">Non</option></select></label></div><?php endforeach;?>
<label>Une observation à partager ?<textarea name="observation" maxlength="5000" rows="4" placeholder="Précisez si nécessaire…"></textarea></label><button class="button primary full">Enregistrer ma MAT</button></form>
<section><div class="section-heading"><div><h2>Mes dernières MAT</h2><p>Les 10 derniers enregistrements.</p></div></div><div class="card-list"><?php if(empty($data['mats'])):?><div class="empty"><span>☀</span><strong>Aucune MAT enregistrée</strong><p>Votre première MAT apparaîtra ici.</p></div><?php endif;?><?php foreach(array_slice($data['mats']??[],0,10) as $mat):?><article class="record-card"><div><small><?=esc($mat['datec']??'')?></small><h3><?=esc($mat['title']??'Mise au travail')?></h3></div><span class="status <?=((int)($mat['severity']??0)===3?'danger':'done')?>"><?=((int)($mat['severity']??0)===3?'Alerte':'Enregistrée')?></span></article><?php endforeach;?></div></section>
<?php elseif($view==='report'):?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">QSSE / AMÉLIORATION CONTINUE</span><h1>Faire une remontée terrain</h1><p>Décrivez les faits simplement. La Direction sera notifiée et pourra déclencher une action PAO.</p></header>
<div class="safety-note"><strong>Danger immédiat ?</strong><p>Appliquez les consignes d’urgence du site et prévenez votre responsable avant d’utiliser ce formulaire.</p></div>
<form action="?view=report" method="post" enctype="multipart/form-data" class="panel form-stack"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="origin" value="terrain"><div class="field-grid"><label>Titre<input name="title" maxlength="180" required value="<?=esc(!$sent?($_POST['title']??''):'')?>" placeholder="Ex. Protection manquante"></label><label>Site / activité<input name="context_label" maxlength="180" value="<?=esc(!$sent?($_POST['context_label']??''):'')?>"></label></div><label>Faits observés<textarea name="description" maxlength="10000" rows="5" required placeholder="Décrivez uniquement ce que vous avez constaté…"><?=esc(!$sent?($_POST['description']??''):'')?></textarea></label><div class="field-grid"><label>Lieu précis<input name="location" maxlength="180"></label><label>Date et heure<input type="datetime-local" name="occurred_at"></label></div><div class="field-grid"><label>Type<select name="report_type" required><option value="dangerous_situation">Situation dangereuse</option><option value="nonconformity">Non-conformité</option><option value="good_point">Bon point</option><option value="improvement_idea">Idée d’amélioration</option></select></label><label>Criticité<select name="severity"><option value="1">Faible — vert</option><option value="2">Modérée — orange</option><option value="3">Élevée — rouge</option></select></label></div><label>Type de non-conformité<select name="nc_type"><option value="">Sans objet</option><option value="documentary">Documentaire</option><option value="operational">Opérationnelle</option><option value="regulatory">Réglementaire</option><option value="material">Matérielle</option><option value="organizational">Organisationnelle</option></select></label><label>Analyse immédiate<textarea name="analysis_immediate" maxlength="5000" rows="3"></textarea></label><label>Action immédiate réalisée<textarea name="immediate_action" maxlength="5000" rows="3"></textarea></label><label>Catégorie<select name="category"><?php foreach(['danger'=>'Déplacement, chute, glissade','ppe'=>'EPI','environment'=>'Environnement','equipment'=>'Outillage, machine, équipement','hazardous_product'=>'Produit dangereux / rayonnement','fire'=>'Incendie / explosion','electricity'=>'Électricité','ergonomics'=>'Ergonomie','workload'=>'Stress et surcharge','other'=>'Autre'] as $k=>$v):?><option value="<?=esc($k)?>"><?=esc($v)?></option><?php endforeach;?></select></label><label class="file-field">Photo du constat <small>JPEG, PNG ou WebP — 2 Mo maximum</small><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label><button class="button primary full">Transmettre ma remontée</button><p class="privacy">Ne renseignez aucune information médicale dans ce formulaire.</p></form>
<?php elseif($view==='records'):?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">QSSE / MES CONTRIBUTIONS</span><h1>Mes remontées</h1><p>Suivez leur prise en compte et leur traitement.</p></header><div class="card-list"><?php if(empty($data['records'])):?><div class="empty"><span>◎</span><strong>Aucune remontée disponible</strong><p>Vos signalements apparaîtront ici.</p><a class="button primary" href="?view=report">Faire une remontée</a></div><?php endif;?><?php foreach($data['records']??[] as $record):?><article class="record-card vertical"><div class="record-top"><small><?=esc($record['ref']??'')?></small><span class="status <?=esc($record['status']??'')?>"><?=esc($states[$record['status']??'']??($record['status']??''))?></span></div><h3><?=esc($record['title']??'')?></h3><p><?=esc($record['description']??'')?></p></article><?php endforeach;?></div>
<?php elseif($view==='actions'):?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">QSSE / PLAN D’ACTION OPÉRATIONNEL</span><h1>Mes actions PAO</h1><p>Consultez vos échéances et rendez compte de l’avancement.</p></header><div class="card-list"><?php if(empty($data['actions'])):?><div class="empty"><span>↗</span><strong>Aucune action affectée</strong><p>Les actions dont vous êtes pilote apparaîtront ici.</p></div><?php endif;?><?php foreach($data['actions']??[] as $pa):?><article class="action-card"><div class="record-top"><small><?=esc($pa['ref']??'')?></small><span class="status <?=esc($pa['status']??'')?>"><?=esc($states[$pa['status']??'']??($pa['status']??''))?></span></div><h3><?=esc($pa['title']??'')?></h3><div class="action-meta"><span><small>Échéance</small><strong><?=esc($pa['due_date']??'—')?></strong></span><span><small>Avancement</small><strong><?=esc($pa['progress']??0)?> %</strong></span></div><div class="progress"><i style="width:<?=max(0,min(100,(int)($pa['progress']??0)))?>%"></i></div><?php if(in_array($pa['status']??'',['open','in_progress'],true)):?><details><summary>Mettre à jour l’action</summary><form action="?view=actions" method="post" class="form-stack compact"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="form" value="action"><input type="hidden" name="action_id" value="<?=(int)($pa['rowid']??0)?>"><input type="hidden" name="version" value="<?=(int)($pa['version']??0)?>"><input type="hidden" name="request_id" value="<?=esc(bin2hex(random_bytes(16)))?>"><label>Nouvel avancement<input type="number" name="progress" min="0" max="100" value="<?=(int)($pa['progress']??0)?>" required></label><label>Compte rendu<textarea name="note" maxlength="5000" required></textarea></label><button class="button primary">Enregistrer</button></form></details><?php endif;?></article><?php endforeach;?></div>
<?php elseif($view==='documents'):?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">QSSE / MAÎTRISE DOCUMENTAIRE</span><h1>Documents applicables</h1><p>Accédez aux références utiles depuis un espace adapté au terrain.</p></header><div class="document-grid"><article class="document-card"><span>◫</span><div><h3>Politique QSSE</h3><p>Orientations et engagements de l’entreprise.</p></div><i>Bientôt disponible</i></article><article class="document-card"><span>▤</span><div><h3>DUER</h3><p>Document unique d’évaluation des risques.</p></div><i>Bientôt disponible</i></article><article class="document-card"><span>✓</span><div><h3>Liste des documents applicables</h3><p>Procédures, consignes et formulaires en vigueur.</p></div><i>Connexion QSSE à finaliser</i></article></div>
<?php else:?>
<?php $sirhPages=['planning'=>['Mon planning','Missions, présences et accès à la MAT.','▦'],'habilitations'=>['Formations et habilitations','Validités et autorisations employeur.','✓'],'companionship'=>['Compagnonnage','Parcours tuteur et suivi des équipiers.','♙'],'directory'=>['Annuaire société','Contacts professionnels accessibles aux équipiers.','☎']];$sirh=$sirhPages[$view];?>
<header class="page-heading"><a class="back" href="?view=home">← Retour</a><span class="eyebrow">ESPACE ÉQUIPIER / SIRH</span><h1><?=esc($sirh[0])?></h1><p><?=esc($sirh[1])?></p></header><section class="provider-card"><span><?=esc($sirh[2])?></span><div><span class="eyebrow">MODULE INDÉPENDANT</span><h2>Emplacement réservé au SIRH</h2><p>Cette page est prête à recevoir les données du module développé par Damien. Le connecteur commun utilisera l’identité LDAP déjà active.</p><ul><li>Navigation et affichage mobile disponibles</li><li>Contrat d’interopérabilité ARCenal prévu</li><li>Aucune donnée SIRH simulée</li></ul></div></section>
<?php endif;?>
</main>
<nav class="bottom-nav" aria-label="Accès rapides"><a href="?view=home"<?=$active('home')?>><span>⌂</span>Accueil</a><a href="?view=mat"<?=$active('mat')?>><span>☀</span>MAT</a><a href="?view=report"<?=$active('report')?>><span>!</span>Remontée</a><a href="?view=actions"<?=$active('actions')?>><span>↗</span>PAO</a></nav>
</div></div></body></html>
