# Passage de relais

Objectif actuel : publier la candidate VIGIlance lot 01 sur testing pour recette preview, sans perte des contrats ARCenal Bridge.

État : testing, base a2e662c. Dossiers SIRH autonomes, stockage SQLite privé, profils/périmètres et panneau d'amorçage. La nouvelle version est 1.1.0~beta1+ynh1. L'absence de Bridge n'empêche plus le SIRH. Les mises à jour conservent config.php et ne modifient aucune permission Dolibarr ; paramètres métier séparés dans application-settings.php. Le panneau historique obsolète est remplacé par un panneau 1.0 pris en charge, limité à l'amorçage administrateur.

Contrôles locaux dans le dépôt applicatif : services, HTTP, navigateur, lifecycle avec helpers doublés et historique QSSE réussis ; revue du diff du paquet avant push. Aucun secret réel, aucune dépendance installée, aucun déploiement. Rapport dans RECETTE_VIGILANCE_LOT01.md.

Prochaine étape : mise à jour explicite de l'instance preview depuis testing, initialisation admin depuis Configuration YunoHost, puis installation/mise à jour/sauvegarde/restauration et SSO réels sur une recette représentative. Les catalogues ARCenal sont figés par révision et demandent leur procédure de promotion ; ils ne sont pas automatiquement modifiés par ce push.
