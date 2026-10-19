<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
$isAdmin=$access->has('admin'); $isCandidate=$access->has('candidate'); $isEmployee=$access->has('employee');
$canFollow=$isAdmin || $access->has('rh') || $access->has('qsse');
$canCandidates=$isAdmin || $access->has('direction');
$notifications=$journey->notifications();
if ($view==='home' && $isCandidate) $view='candidate';
$nav=['home'=>'Tableau de bord'];
if ($canCandidates) $nav['candidates']='Candidatures';
if ($isCandidate) $nav['candidate']='Mon dossier de candidature';
if ($isEmployee) $nav['welcome']='Mon accueil entreprise';
if ($canFollow) $nav['followup']='Suivi de l’accueil';
if ($access->has('rh') || $access->has('manager')) $nav['people']=$access->has('rh')?'Collaborateurs':'Mon équipe';
if ($isAdmin) { $nav['welcome-contents']='Contenus d’accueil'; $nav['settings']='Paramètres'; }
if ($isEmployee && !$welcome->blocked()) $nav['mine']='Mon espace · Mon dossier';
if ($qsse && ($access->has('qsse') || $access->has('crp'))) $nav['mat']='QSSE · Ma MAT';
$nav['notifications']='Notifications';
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="referrer" content="no-referrer"><title>VIGIlance — <?= $e($company['name']) ?></title><link rel="stylesheet" href="journey.css"><script src="journey.js" defer></script></head>
<body><header class="topbar"><a class="client-brand" href="?view=home" aria-label="VIGIlance, accueil"><?php if($hasBrandLogo): ?><img src="?view=logo" alt="Logo <?= $e($company['name']) ?>"><?php else: ?><strong><?= $e($company['name']) ?></strong><?php endif; ?></a><div class="topbar-product"><strong>VIGIlance</strong><span>Votre entreprise, vos équipes.</span></div><div class="topbar-actions"><a class="notification-button" href="?view=notifications" aria-label="Ouvrir les notifications"><?= vigilanceJourneyIcon('bell') ?><span><?= count($notifications) ?></span></a><div class="user-identity"><span class="avatar"><?= $e(mb_strtoupper(mb_substr($uid,0,2))) ?></span><span><strong><?= $e($uid) ?></strong><small><?= $e($labels[$profile]??$profile) ?></small></span></div></div></header>
<div class="layout"><aside class="sidebar" aria-label="Navigation principale"><div class="profile-box">
<?php if(count($account['roles'])>1): ?><form method="post" action="?view=home"><?= $csrf ?><input type="hidden" name="action" value="profile"><label for="profile-selector">Profil actif</label><select id="profile-selector" name="profile"><?php foreach($account['roles'] as $role): ?><option value="<?= $e($role) ?>" <?= $profile===$role?'selected':'' ?>><?= $e($labels[$role]??$role) ?></option><?php endforeach; ?></select><button class="button secondary small">Changer de profil</button></form><?php else: ?><span class="muted">Profil actif</span><strong><?= $e($labels[$profile]??$profile) ?></strong><?php endif; ?>
</div><p class="nav-title">MON ESPACE</p><nav><?php foreach($nav as $target=>$label): ?><a class="nav-link <?= $target===$view?'active':'' ?>" href="?view=<?= $e($target) ?>" <?= $target===$view?'aria-current="page"':'' ?>><?= vigilanceJourneyIcon((['home'=>'dashboard','candidates'=>'people','followup'=>'people','settings'=>'settings','notifications'=>'bell'])[$target]??'document') ?><?= $e($label) ?></a><?php endforeach; ?></nav><div class="sidebar-bottom"><?= $e($company['name']) ?></div></aside><main id="main" tabindex="-1">
<?php if($error): ?><p class="app-alert error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<?php if($notice): ?><p class="app-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php
if ($view==='home' || $view==='followup'): require __DIR__.'/journey-dashboard.php';
elseif ($view==='candidates' || $view==='candidate' || $view==='offer'): require __DIR__.'/journey-candidate.php';
elseif ($view==='welcome-contents'): require __DIR__.'/journey-contents.php';
elseif ($view==='welcome'): require __DIR__.'/journey-welcome.php';
elseif ($view==='notifications'): ?>
<div class="hero"><div><p class="eyebrow">MON ESPACE</p><h1>Notifications</h1><p class="subtitle">Les événements de vos démarches.</p></div></div><section class="card"><?php if(!$notifications): ?><p class="empty">Aucune notification pour le moment.</p><?php endif; ?><?php foreach($notifications as $item): ?><article class="notification-item"><strong><?= $e($item['title']) ?></strong><p><?= $e($item['body']) ?></p><small class="muted"><?= $e($item['created_at']) ?></small></article><?php endforeach; ?></section>
<?php endif; ?>
</main></div><footer class="editor-signature">Propulsé par <strong>ARCenal OS</strong></footer></body></html>
