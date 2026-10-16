# Passage de relais

Objectif actuel : publier le premier incrément réel après validation de la maquette : identité Gratitude et logo YunoHost automatique, sur la branche preview.

État : preview, base 0740d8a, candidate 1.1.0~beta2+ynh1. Cadre PHP réel repris du rendu Gratitude, logo du domaine principal lu automatiquement avec cache privé, signature stable et sélecteur de profil fonctionnant avec ou sans JavaScript. Les services et contrôles métier existants sont conservés. Configuration Bridge inchangée ; branding_origin ajouté seulement à application-settings.php. Aucun changement de schéma.

Contrôles locaux dans le dépôt applicatif : services, HTTP, navigateur, lifecycle avec helpers doublés et historique QSSE réussis ; revue du diff du paquet avant push. Aucun secret réel, aucune dépendance installée, aucun déploiement. Rapport dans RECETTE_VIGILANCE_LOT01.md.

Historique lot 01 : premier commit paquet 7fd8942, script de configuration exécutable dans 85aec17 ; sources applicatives publiées dans 1947397. CI source 38621004804 réussie (code 1947397), y compris régression connectée historique, services et navigateur autonome. Ce résultat qualifiait le socle antérieur ; l'incrément actuel est décrit ci-dessous.

Vérifications locales de cet incrément : 38 assertions branding, 59 services, 29 HTTP, 125 navigateur et 18 lifecycle avec helpers doublés ; 77 contrôles QSSE historiques. Lecture du vrai logo public ONYX vérifiée avec serveur applicatif de test et données fictives. Le domaine principal est déterminé par une commande YunoHost existante, dont l'exécution réelle sera reçue sur l'environnement client.

Source publiée : commit 801c563df41771ce99a7ef907c92e95c06d379ef, branche preview/vigilance-lot01 du dépôt applicatif. Recette GitHub 38860271173 réussie : syntaxe, branding, services autonomes, HTTP/navigateur, installation et cycle Dolibarr historique, mail capturé, API signée et build. Revue indépendante du présent paquet favorable, sans défaut important démontré. Cette CI ne constitue pas une recette YunoHost de l'installation cliente.

Prochaine étape : recette cliente du rendu et du cycle YunoHost, puis projection des groupes YunoHost dans les profils et parcours candidat/accueil par incréments. Les catalogues ARCenal sont figés par révision et demandent leur procédure de promotion ciblée. Aucune installation serveur ni réception client revendiquée par ces contrôles locaux.
