# VIGIlance — lot 01, exploitation et recette

Candidate : portail `1.1.0-beta.1`, manifeste `1.1.0~beta1+ynh1`, supérieur au paquet Portail 1.0.x déjà publié. Le module historique QSSE demeure `0.4.15-beta.1`. Aucun identifiant de module, table ou application installée n'est renommé.

## Architecture et stockage

Application PHP existante, services locaux et vues serveur ; YunoHost fournit l'identité validée par SSOwat via `REMOTE_USER`. Les en-têtes HTTP d'identité ne sont pas acceptés comme authentification. Vérifier sur l'instance cible la production de ce paramètre par le proxy et l'interdiction d'un accès direct contournant SSO.

Une instance YunoHost correspond à une entreprise active configurée côté serveur. Les tables portent un identifiant d'entreprise et les services contrôlent cette frontière ; cela ne revendique pas un portail multi-entreprises administrable depuis l'interface de ce lot.

SQLite via PDO, fichier `$data_dir/vigilance.sqlite`, répertoire 0700 et fichier 0600. Journal DELETE et synchronisation FULL ; écritures sérialisées avec `BEGIN IMMEDIATE`, délai d'attente 5 secondes, révisions optimistes et contraintes d'unicité. Ce choix permet une base privée sans service supplémentaire pour le pilote ; la charge réelle et les accès fortement concurrents restent à mesurer avant commercialisation correspondante.

La migration `001-dossiers.sql` est transactionnelle, versionnée et contrôlée par SHA-256. Ne jamais modifier une migration après son déploiement : ajouter la suivante. Les requêtes web vérifient le schéma, elles ne migrent pas automatiquement.

ARCenal Bridge reste propriétaire de config.php et des permissions de passerelle. Les paramètres de base/entreprise/admin VIGIlance sont conservés séparément dans application-settings.php, privé et sauvegardé. Un remplacement de config.php par Bridge ne supprime donc pas l'accès au stockage des dossiers. Le statut paired de Bridge est respecté pour l'activation de la connexion QSSE ; le SIRH n'en dépend pas.

## Rôles et parcours

- **Admin** : entreprise/logo et permissions ; aucun accès dossier RH implicite. Le premier admin est choisi à l'installation ou amorcé explicitement côté système après mise à jour.
- **RH** : dossiers de l'entreprise, création/modification/archivage et association d'un compte autorisé.
- **Manager** : données professionnelles des équipes attribuées, sans champs administratifs.
- **Collaborateur** : propre dossier ; pas de modification RH ; annuaire professionnel si permission distincte.
- **Direction** : compteurs agrégés par situation, pas de lecture nominative sans rôle RH.
- **QSSE, CRP, Tuteur** : aucune permission RH implicite. QSSE/CRP peuvent accéder à la connexion QSSE historique configurée.

Un compte peut cumuler des rôles, mais choisit son profil actif parmi ceux attribués ; chaque requête relit son autorisation. Révocation : refus à la prochaine requête. Le dernier administrateur actif ne peut pas être supprimé de ses droits. L'archivage d'un collaborateur ne désactive pas son compte LDAP ; il conserve son dossier et peut toujours consulter son propre historique si son accès est autorisé.

Les confirmations sont affichées à l'écran ; aucun mail de paie ou notification RH n'est ajouté dans ce lot. Audit : auteur, opération, cible, révision, date, sans copie du contenu des dossiers.

## Mise à jour non destructive

1. Inventorier version installée, identifiant d'app et chemins, configuration, UID autorisés et données réelles ; ne pas déduire l'installation à partir du seul dépôt.
2. Sauvegarder l'installation existante et vérifier une restauration sur copie. Mettre les écritures en maintenance avant toute restauration ou changement incompatible.
3. Construire le paquet avec `python3 scripts/package.py`. Le script synchronise les sources du portail dans `portal_ynh/sources/portal/`, sans embarquer de base ou fixtures.
4. Tester une installation neuve et une mise à jour représentative via YunoHost ; vérifier la nouvelle dépendance PHP SQLite déclarée avant d'autoriser son installation. Aucun paquet système n'est installé par le build local.
5. La mise à jour copie les répertoires de code gérés, conserve les paramètres de passerelle et les autres paramètres privés, applique le schéma et rouvre l'application après succès. Un échec maintient le marqueur de maintenance ; corriger ou restaurer avant réouverture.
6. Sur une ancienne installation, choisir le premier admin dans le panneau Configuration de l'application YunoHost, enregistrer ce choix puis utiliser le bouton d'initialisation, ou utiliser la commande CLI documentée. L'utilisateur est vérifié dans YunoHost. Autoriser ensuite les profils existants ; pas de transfert automatique des droits numériques Dolibarr et pas d'import de dossiers ou pièces provenant de ses modules.

## Sauvegarde et restauration

`scripts/backup` produit un snapshot SQLite cohérent via `VACUUM INTO`, conservé dans `$install_dir/.backup/vigilance.sqlite` jusqu'à la copie effective par le helper YunoHost. Il sauvegarde code, configuration, snapshot, logos privés et configuration nginx/PHP-FPM. Les logos utilisent des noms dérivés du contenu et ne sont pas supprimés lors de leur remplacement, ce qui préserve les références d'un snapshot antérieur. Ne pas supprimer `.backup/` entre la déclaration et la copie de la sauvegarde.

`scripts/restore` restaure ces chemins via les helpers, contrôle version/empreinte/intégrité du snapshot et restaure atomiquement la base vers le chemin configuré. Les propriétaires et permissions privés sont réappliqués avant réouverture. La restauration se fait en fenêtre de maintenance, sans écritures actives ; ce lot ne revendique pas de restauration à chaud.

Retour arrière : restaurer ensemble base, configuration, fichiers et version du code correspondant au même snapshot. Une ancienne version sans support du schéma autonome ne doit pas être utilisée comme solution de récupération des nouveaux dossiers. Conserver les sauvegardes d'avant mise à jour pour restaurer l'état historique, en rapprochant les écritures intervenues depuis.

## Contrôles reproductibles

```sh
php tests/portal.php
python3 tests/portal-http.py
# Avec Playwright et son navigateur déjà disponibles :
python3 tests/portal-http.py --browser
php tests/unit.php
php tests/storage.php
python3 scripts/package.py
python3 tests/portal-lifecycle.py
```

Les tests emploient une base temporaire et des identités fictives. Le routeur `tests/portal-fixture.php` remplace le proxy SSO uniquement pour le serveur de test local ; il n'est jamais distribué ni déployé.

La recette locale porte sur persistance SQLite réelle, droits/rejet IDOR, entreprise, CSRF, échappement, concurrence par révision, rollback audit, snapshot, UI HTTP et navigateur. Le test navigateur utilise Chrome déjà installé (canal configurable par VIGILANCE_BROWSER_CHANNEL) et ne télécharge rien. Le test de cycle de vie exécute les scripts avec des doubles de helpers YunoHost et vérifie leurs effets sur des fixtures. Ces tests ne remplacent pas les preuves du cycle YunoHost réel, du SSO cible, de charge ou du portage métier QSSE. Voir RECETTE_VIGILANCE_LOT01.md pour résultats et réserves.

## Périmètre restant

Coffre-fort, bulletins de paie, onboarding complet, planning autonome, portage QSSE et export des indicateurs Dolibarr restent des lots ultérieurs. Les indicateurs présents dans l'accueil Direction ne sont pas encore transmis à Dolibarr. La V1 complète n'est pas livrée par ce premier socle.
