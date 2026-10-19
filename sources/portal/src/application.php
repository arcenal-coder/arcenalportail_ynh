<?php
// SPDX-License-Identifier: GPL-3.0-or-later

function vigilanceApplication(array $context, string $view): void
{
    extract($context, EXTR_SKIP);
    $people = new \Vigilance\PersonService($db,$access);
    $admin = new \Vigilance\AdminService($db,$access);
    $branding = new \Vigilance\Branding($db->directory.'/branding',(string)($config['branding_origin'] ?? ''));
    if ($view === 'logo') {
        $image = $branding->image();
        if ($image !== null) {
            header('Content-Type: '.$image['mime']);
            header('Content-Length: '.strlen($image['body']));
            header('Content-Security-Policy: default-src \'none\'; sandbox');
            print $image['body'];
            return;
        }
        $logo = $admin->company()['logo'];
        if (!preg_match('/^[a-f0-9]{64}\.(png|jpg|webp)$/D',$logo)) throw new DomainException('Logo non disponible.');
        $path = $db->directory.'/logos/'.$logo;
        if (!is_file($path)) throw new DomainException('Logo non disponible.');
        header('Content-Type: '.(['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][pathinfo($logo,PATHINFO_EXTENSION)]));
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        readfile($path);
        return;
    }
    $labels = ['employee'=>'Équipier','rh'=>'RH','manager'=>'Manager','direction'=>'Direction','admin'=>'Administration','qsse'=>'QSSE','crp'=>'CRP','tutor'=>'Tuteur','candidate'=>'Candidat','rp'=>'RP'];
    $statuses = ['candidate'=>'Candidat','onboarding'=>'En intégration','active'=>'Actif','leaving'=>'En sortie','archived'=>'Archivé'];
    $error = '';
    $notice = $_SESSION['notice'] ?? '';
    unset($_SESSION['notice']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) throw new DomainException('Formulaire expiré.');
        try {
            $successMessage = 'Enregistrement effectué.';
            $action = $_POST['action'] ?? '';
            $destination = '?view=home';
            if ($action === 'profile') {
                $selected = $_POST['profile'] ?? '';
                if (!is_string($selected) || !in_array($selected,$access->account()['roles'],true)) throw new DomainException('Profil non autorisé.');
                $_SESSION['profile'] = $selected;
            } elseif ($action === 'person') {
                $input = array_diff_key($_POST,array_flip(['action','csrf','id','revision']));
                $id = $_POST['id'] ?? '';
                if (!is_string($id)) throw new InvalidArgumentException('Dossier invalide.');
                if ($id === '') $id = $people->create($input);
                else $people->update($id,(int)($_POST['revision'] ?? 0),$input);
                $destination = '?view=person&id='.rawurlencode($id);
            } elseif ($action === 'company') {
                $admin->rename((string)($_POST['name'] ?? ''),(int)($_POST['revision'] ?? 0));
                $destination = '?view=settings';
            } elseif ($action === 'branding-refresh') {
                $access->require('admin');
                $refreshed = $branding->resolve(true);
                if (!$refreshed['enabled']) throw new InvalidArgumentException('La source du logo YunoHost n’est pas configurée pour cette application.');
                $successMessage = $refreshed['error'] ? 'La synchronisation YunoHost est indisponible. L’application reste accessible.' : ($refreshed['available'] ? 'Le logo YunoHost a été vérifié.' : 'Aucun logo exploitable n’est déclaré dans YunoHost.');
                $destination = '?view=settings';
            } elseif ($action === 'account') {
                if (!is_array($_POST['roles'] ?? [])) throw new InvalidArgumentException('Rôles invalides.');
                $teams = array_values(array_filter(array_map('trim',explode(',',(string)($_POST['teams'] ?? ''))),static fn($team) => $team !== ''));
                $mail = $_POST['notification_email'] ?? null;
                if ($mail !== null && !is_string($mail)) throw new InvalidArgumentException('Adresse e-mail invalide.');
                $admin->updateAccount((string)($_POST['uid'] ?? ''),(int)($_POST['revision'] ?? 0),$_POST['roles'] ?? [],$teams,isset($_POST['enabled']),isset($_POST['directory']),$mail);
                $destination = '?view=settings';
            } elseif ($action === 'mail-retry') {
                (new \Vigilance\JourneyMail($db))->retryFailed($access);
                $destination = '?view=settings';
            } elseif ($action === 'logo') {
                $access->require('admin');
                $upload = $_FILES['logo'] ?? [];
                if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '') || (int)($upload['size'] ?? 0) > 2097152) throw new InvalidArgumentException('Logo invalide ou supérieur à 2 Mo.');
                $admin->setLogo(file_get_contents($upload['tmp_name']),(int)($_POST['revision'] ?? 0));
                $destination = '?view=settings';
            } else throw new InvalidArgumentException('Action inconnue.');
            $_SESSION['notice'] = $successMessage;
            header('Location: '.$destination,true,303);
            return;
        } catch (DomainException | InvalidArgumentException $failure) {
            http_response_code($failure instanceof DomainException ? 403 : 422);
            $error = $failure->getMessage();
        }
    }
    $company = $admin->company();
    $brandingStatus = $branding->resolve();
    $hasBrandLogo = $brandingStatus['available'] || $company['logo'] !== '';
    $isRh = $access->has('rh');
    $isManager = $access->has('manager');
    $isEmployee = $access->has('employee');
    $isAdmin = $access->has('admin');
    $isDirection = $access->has('direction');
    $canDirectory = (int)$access->account()['directory'] === 1 || $isRh || $isManager;
    $canQsse = $qsse && ($access->has('qsse') || $access->has('crp'));
    $person = null;
    $listing = null;
    $query = is_string($_GET['q'] ?? '') ? ($_GET['q'] ?? '') : '';
    if (in_array($view,['people','directory'],true)) {
        if ($view === 'people' && !$isRh && !$isManager) throw new DomainException('Liste non autorisée.');
        $listing = $people->listing($query,(int)($_GET['page'] ?? 1),$view === 'directory');
    } elseif ($view === 'person') {
        $person = $people->get((string)($_GET['id'] ?? ''),($_GET['directory'] ?? '') === '1');
    } elseif ($view === 'new') {
        $access->require('rh');
        $person = array_fill_keys(\Vigilance\PersonService::FIELDS,'');
        $person['status'] = 'candidate';
    } elseif ($view === 'mine') {
        $person = $people->mine();
    } elseif ($view === 'settings') {
        $access->require('admin');
        $accounts = $admin->accounts();
        $mailStatus = (new \Vigilance\JourneyMail($db))->status($access);
    } elseif ($view !== 'home') throw new DomainException('Écran non disponible.');
    $choices = $isRh && in_array($view,['new','person'],true) ? $admin->accountChoices() : [];
    $indicators = $isDirection ? $admin->indicators() : [];
    $e = static fn($value) => htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $csrf = '<input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']).'">';
    require __DIR__.'/../views/application.php';
}
