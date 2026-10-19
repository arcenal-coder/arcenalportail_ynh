# Passage de relais

## E-mails des workflows — beta5

Source 359ab24ea28b38c916af4705eadfcffdec717373, candidate 1.1.0-beta5~ynh1, branche preview. Six transitions raccordées, file persistante, paramètres des adresses et suivi/relance Administration. Voir VIGILANCE_WORKFLOW_EMAILS.md. Migration003 additive, sauvegarde de la file/cron, aucune donnée personnelle importée. Contrôles locaux : 48 mail,103 parcours,59 services,43 branding,34 HTTP,28 outils,26 lifecycle doublé ; revue indépendante favorable après correction d'équité de file. Qualification native du commit exact à exécuter avant promotion ciblée ; preuve MTA locale exigée après chaque upgrade dont beta4 ynh2. Le contrôle vérifie le cron rendu mais déclenche manuellement le worker ; passage effectif du cron et réception cliente restent distincts. Aucun déploiement client.

## Correctif du 19 octobre 2026 — beta4 ynh2

Vidéo 1 Go et signalement fiable des échecs d’actualisation du logo. Voir `VIGILANCE_IMPORT_VIDEO_1GO.md`. Contrôles locaux et revue indépendante favorables ; qualification YunoHost native à exécuter sur le commit publié avant promotion Preview. Aucun déploiement client. La panne logo propre à l’instance cliente n’est pas reproduite depuis le Mac et reste à recevoir.

## Reprise du 19 octobre 2026 — beta4

Source applicative publiée : `624f6368e3e1598cbc79a14ff526e80f7de4264f`. Paquet installable sur la branche Preview exacte, version `1.1.0-beta4~ynh1`. La maquette locale approuvée est raccordée au stockage réel, sans données d’exemple. Voir `VIGILANCE_BETA4.md` pour les fonctions, les limites et le retour arrière.

Contrôles locaux réussis : 71 assertions nouveaux parcours, 36 contrôles navigateur métier, 59 services du socle, 29 HTTP, 125 navigateur du socle, 19 cycle de vie avec helpers doublés. Revue indépendante favorable après correction de l’arrondi vidéo ; contrôle précommit sans secret. La CI complète source `39215834554` a réussi sur `fcd7b04` avant la correction ciblée `624f636`, dont la CI est relancée. La qualification native du commit paquet et sa recette cliente restent à exécuter ; le catalogue doit conserver la précédente candidate qualifiée jusqu’à succès. Aucun déploiement sur le serveur client.

La suite conserve l’historique du lot visuel précédent.

Objectif actuel : publier le premier incrément réel après validation de la maquette : identité Gratitude et logo YunoHost automatique, sur la branche preview.

État : preview, base 0740d8a, candidate 1.1.0~beta2+ynh1. Cadre PHP réel repris du rendu Gratitude, logo du domaine principal lu automatiquement avec cache privé, signature stable et sélecteur de profil fonctionnant avec ou sans JavaScript. Les services et contrôles métier existants sont conservés. Configuration Bridge inchangée ; branding_origin ajouté seulement à application-settings.php. Aucun changement de schéma.

Contrôles locaux dans le dépôt applicatif : services, HTTP, navigateur, lifecycle avec helpers doublés et historique QSSE réussis ; revue du diff du paquet avant push. Aucun secret réel, aucune dépendance installée, aucun déploiement. Rapport dans RECETTE_VIGILANCE_LOT01.md.

Historique lot 01 : premier commit paquet 7fd8942, script de configuration exécutable dans 85aec17 ; sources applicatives publiées dans 1947397. CI source 38621004804 réussie (code 1947397), y compris régression connectée historique, services et navigateur autonome. Ce résultat qualifiait le socle antérieur ; l'incrément actuel est décrit ci-dessous.

Vérifications locales de cet incrément : 38 assertions branding, 59 services, 29 HTTP, 125 navigateur et 18 lifecycle avec helpers doublés ; 77 contrôles QSSE historiques. Lecture du vrai logo public ONYX vérifiée avec serveur applicatif de test et données fictives. Le domaine principal est déterminé par une commande YunoHost existante, dont l'exécution réelle sera reçue sur l'environnement client.

Source publiée : commit 801c563df41771ce99a7ef907c92e95c06d379ef, branche preview/vigilance-lot01 du dépôt applicatif. Recette GitHub 38860271173 réussie : syntaxe, branding, services autonomes, HTTP/navigateur, installation et cycle Dolibarr historique, mail capturé, API signée et build. Revue indépendante du présent paquet favorable, sans défaut important démontré. Cette CI ne constitue pas une recette YunoHost de l'installation cliente.

Prochaine étape : recette cliente du rendu et du cycle YunoHost, puis projection des groupes YunoHost dans les profils et parcours candidat/accueil par incréments. Les catalogues ARCenal sont figés par révision et demandent leur procédure de promotion ciblée. Aucune installation serveur ni réception client revendiquée par ces contrôles locaux.
