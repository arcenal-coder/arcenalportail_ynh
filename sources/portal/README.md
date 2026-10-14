# VIGIlance — portail et dossiers autonomes · 1.1.0-beta.1

Le paquet YunoHost v2 conserve l'identifiant installé `arcenalportail`. Le nom visible devient VIGIlance, avec la signature « Propulsé par ARCenal OS ». PHP-FPM sert seulement `public/` ; services, migrations, configuration et données restent hors du web.

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

Les profils SIRH n'appellent pas cette passerelle. Un module non configuré n'affiche pas de fonctions QSSE et refuse ses routes directes. Une panne de la connexion optionnelle ne bloque pas les dossiers.

## Exploitation et qualification

Voir [VIGILANCE_LOT01.md](../docs/VIGILANCE_LOT01.md) pour mise à jour, sauvegarde cohérente, restauration, rollback et commandes de test. La recette YunoHost/SSO sur une copie représentative reste requise avant déploiement client. Licence GPL-3.0-or-later, texte dans COPYING.
