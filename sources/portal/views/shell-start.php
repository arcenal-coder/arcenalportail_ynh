<?php // SPDX-License-Identifier: GPL-3.0-or-later ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>VIGIlance — <?= $e($company['name']) ?></title>
  <link rel="stylesheet" href="vigilance.css">
  <script src="interface.js" defer></script>
</head>
<body>
<header class="app-topbar">
  <a class="company-brand" href="?view=home" aria-label="VIGIlance, accueil">
    <?php if($hasBrandLogo): ?>
      <img class="client-logo" src="?view=logo" alt="Logo <?= $e($company['name']) ?>">
    <?php else: ?><span class="company-name"><?= $e($company['name']) ?></span><?php endif; ?>
  </a>
  <div class="app-brand"><strong>VIGIlance</strong><span><?= $e($company['name']) ?></span></div>
  <div class="signed-in"><span class="avatar"><?= $e(mb_strtoupper(mb_substr($uid,0,2))) ?></span><span><strong><?= $e($uid) ?></strong><small><?= $e($labels[$profile]??'Accès autorisé') ?></small></span></div>
</header>
<div class="shell">
  <aside class="rail" aria-label="Navigation principale">
    <section class="identity">
      <?php if(count($account['roles'])>1): ?>
      <form method="post" class="profile-switch" data-profile-switch>
        <?= $csrf ?><input type="hidden" name="action" value="profile">
        <label for="profile-selector">Profil actif</label>
        <select id="profile-selector" name="profile">
          <?php foreach($account['roles'] as $role): ?><option value="<?= $e($role) ?>" <?= $profile===$role?'selected':'' ?>><?= $e($labels[$role]??$role) ?></option><?php endforeach; ?>
        </select>
        <button type="submit">Changer de profil</button>
      </form>
      <?php else: ?><span class="profile-label">Profil actif</span><strong class="active-profile"><?= $e($labels[$profile]??'Accès autorisé') ?></strong><?php endif; ?>
    </section>
    <p class="nav-title">MON ESPACE</p>
    <nav>
      <a href="?view=home" <?= $view==='home'?'aria-current="page"':'' ?>>Tableau de bord</a>
      <?php if($isRh || $isManager): ?><a href="?view=people" <?= in_array($view,['people','person','new'],true)?'aria-current="page"':'' ?>><?= $isRh?'Collaborateurs':'Mon équipe' ?></a><?php endif; ?>
      <?php if($canDirectory): ?><a href="?view=directory" <?= $view==='directory'?'aria-current="page"':'' ?>>Annuaire</a><?php endif; ?>
      <?php if($isEmployee): ?><a href="?view=mine" <?= $view==='mine'?'aria-current="page"':'' ?>>Mon espace · Mon dossier</a><?php endif; ?>
      <?php if($canQsse): ?><a href="?view=mat">QSSE · Ma MAT</a><a href="?view=report">Remontées</a><a href="?view=actions">Actions PAO</a><?php endif; ?>
      <?php if($isAdmin): ?><a href="?view=settings" <?= $view==='settings'?'aria-current="page"':'' ?>>Paramètres</a><?php endif; ?>
    </nav>
    <p class="rail-company"><?= $e($company['name']) ?></p>
  </aside>
  <main class="content" id="main-content">
  <?php if($error): ?><p class="alert error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <?php if($notice): ?><p class="alert success" role="status"><?= $e($notice) ?></p><?php endif; ?>
