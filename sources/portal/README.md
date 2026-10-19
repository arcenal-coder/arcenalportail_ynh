# VIGIlance — portail et dossiers autonomes · 1.1.0-beta.2

Le paquet YunoHost v2 conserve l'identifiant installé `arcenalportail`. Le nom visible devient VIGIlance, avec la signature « Propulsé par ARCenal OS ». PHP-FPM sert seulement `public/` ; services, migrations, configuration et données restent hors du web.

## Rendu intégré — lot 02

Le cadre réel reprend le thème Gratitude examiné en maquette : palette crème/vert léger, accent terracotta, typographie sans serif et cartes claires. Logo sans monogramme décoratif en haut à gauche, identité VIGIlance et signature éditeur ancrée en bas à droite. Le sélecteur de profil existant soumet un POST protégé ; il reste utilisable sans JavaScript.

Le domaine principal est déterminé automatiquement à l'installation/mise à jour avec `yunohost domain main-domain --output-as json`. L'origine HTTPS est stockée dans `application-settings.php`, séparée de Bridge. Le logo est lu dans la configuration publique de ce domaine, avec cache privé de cinq minutes ; une indisponibilité conserve le dernier logo valide ou le repli existant. Le sous-domaine applicatif peut ainsi rester sans logo propre.

Les paramètres proposent « Actualiser depuis YunoHost » ; le dépôt manuel historique est conservé comme logo de secours. Aucun secret ni cookie n'est envoyé à la source de branding. Voir [le rapport et les limites du lot 02](../docs/VIGILANCE_LOT02_IDENTITE.md).

## Fonctions du lot 01

- Dossiers professionnels avec identifiant stable, situation et dates administratives, compte associé explicitement, contrôle de révision et audit transactionnel.
- Profil RH, équipe du manager, Mon dossier salarié, annuaire sur permission, indicateurs Direction et administration séparée des droits RH.
- Entreprise et logo client configurables ; rôles/perimètres vérifiés à chaque requête, même après révocation dans une session existante.
- Stockage SQLite privé : transactions et contraintes, pas de serveur de base supplémentaire. PHP PDO SQLite est déclaré dans le packaging.
- Aucune URL/clé ou disponibilité Dolibarr exigée par ces fonctions.

## Installation et reprise

Lors d'une installation neuve, choisir explicitement un utilisateur YunoHost comme administrateur initial et le nom de l'entreprise. Aucun secret de passerelle n'est demandé ; ARCenal Bridge conserve la propriété de la liaison QSSE historique. Aucun utilisateur n'est promu au premier accès.

Après connexion SSO, un nouvel utilisateur apparaît **en attente** dans Paramètres ; un administrateur lui attribue les rôles et périmètres avant d'activer son accès. RH peut ensuite l'associer à un dossier. Sans dossier associé, le salarié voit un message explicite.

Une mise à jour d'une ancienne installation conserve ses paramètres et ajoute le stockage autonome. Elle ne suppose pas que l'ancien utilisateur QSSE est administrateur RH. Dans l'administration YunoHost, ouvrir l'application → Configuration → Administrateur initial, sélectionner l'utilisateur puis appliquer et cliquer « Initialiser le premier administrateur ». L'action est refusée si un administrateur actif existe déjà. Alternative système avec UID YunoHost vérifié :

Exemple pour l'identifiant `arcenalportail` et son chemin par défaut :

```sh
sudo -u arcenalportail env ARCENAL_PORTAL_CONFIG=/var/www/arcenalportail/config.php \
  php8.2 /var/www/arcenalportail/bin/manage.php bootstrap UID_YUNOHOST_VERIFIE
```

Pour une instance multiple, utiliser l'identifiant et les chemins réels. L'amorçage est refusé si un administrateur actif existe déjà. Les accès au groupe YunoHost autorisé restent gérés par YunoHost ; ils ne remplacent pas les droits applicatifs.

## Compatibilité QSSE

Les fonctions historiques MAT, remontées et PAO restent accessibles avec une passerelle configurée et un profil QSSE/CRP explicitement autorisé. Le module vérifie aussi l'identité côté passerelle. Les pièces historiques ne sont pas déplacées et les secrets ne sont pas envoyés au navigateur. Les connexions et documents associés à Dolibarr seront repris dans un lot de portage QSSE autonome ; ce portage n'est pas revendiqué ici.

## Parcours de la démonstration, avec stockage réel

Le tableau de bord, les candidatures, les propositions FORM S 13, les contenus administrables et l'accueil entreprise reprennent la maquette locale approuvée. L'installation ne crée ni personne fictive, ni média, ni quiz d'exemple.

L'Administration ouvre un dossier pour une identité SSO déjà observée. Le profil Candidat permet de saisir son dossier et d'accepter ou de refuser la proposition courante. Le refus notifie la Direction ; une nouvelle proposition est requise pour poursuivre. L'acceptation ouvre le profil Équipier et l'accueil obligatoire.

Administration → Contenus d'accueil permet d'importer une vidéo MP4/WebM (256 Mo maximum), les quatre PDF (20 Mo chacun), un QR de communication (2 Mo) et de créer le quiz (questions, choix, corrigé, points). Le seuil de réussite est de 26/30. Chaque publication demande explicitement si une nouvelle démarche est obligatoire pour les équipiers déjà accueillis. Les fichiers sont privés sous `welcome/`, à côté de la base, hors racine web ; ils sont inclus dans les scripts de sauvegarde/restauration. La lecture vidéo accepte les requêtes HTTP partielles et sa progression est contrôlée par temps écoulé côté serveur. Cela trace la lecture, sans prétendre mesurer l'attention humaine.

La migration additive `002-journeys.sql` est exécutée par l'outil de gestion pendant l'installation/mise à jour. Aucun dossier existant n'est effacé. Les notifications sont actuellement internes à l'application ; l'envoi des e-mails d'invitation et de suivi reste à raccorder. Les rôles sont applicatifs ; la projection des groupes LDAP YunoHost reste à intégrer. La liste définitive des pièces candidates sera configurée dans un lot suivant.

Pour examiner une instance locale vide, `dev/preview.php`, hors du paquet distribué, peut initialiser un stockage dédié puis servir l'application uniquement sur `127.0.0.1:8771`. Le compte local de prévisualisation est technique et n'est jamais intégré au portail distribué.

Les profils SIRH n'appellent pas cette passerelle. Un module non configuré n'affiche pas de fonctions QSSE et refuse ses routes directes. Une panne de la connexion optionnelle ne bloque pas les dossiers.

## Exploitation et qualification

Voir [VIGILANCE_LOT01.md](../docs/VIGILANCE_LOT01.md) pour mise à jour, sauvegarde cohérente, restauration, rollback et commandes de test. La recette YunoHost/SSO sur une copie représentative reste requise avant déploiement client. Licence GPL-3.0-or-later, texte dans COPYING.
