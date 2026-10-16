# VIGIlance pour YunoHost — candidate preview 1.1.0-beta.2

*[Read this README in English.](./README.md)*

VIGIlance propose dossiers collaborateurs autonomes, accès personnel, gestion RH, périmètres manager et permissions explicites. YunoHost SSO authentifie les comptes. Dolibarr est destiné aux dashboards d'indicateurs futurs et n'est pas requis pour ces fonctions. L'identifiant installé historique `arcenalportail` est conservé.

## Mise à jour preview

Paquet candidat : **1.1.0~beta2+ynh1**, branche **preview**.
URL source : https://github.com/arcenal-coder/arcenalportail_ynh/tree/preview

Cet incrément intègre le thème Gratitude validé dans le portail PHP réel, reprend automatiquement le logo déclaré sur le domaine principal YunoHost et ancre la signature éditeur en bas à droite. Le domaine principal est déterminé par la CLI YunoHost ; les paramètres de branding restent séparés de Bridge. Le changement de profil existant reste contrôlé côté serveur, y compris sans JavaScript. La correspondance réelle des groupes YunoHost et les parcours candidat/accueil relèvent des incréments suivants.

Utiliser une instance preview représentative et une sauvegarde vérifiée. Une installation neuve choisit explicitement l'administrateur initial et l'entreprise. Après mise à jour d'une ancienne installation, ouvrir Configuration dans l'application YunoHost, sélectionner l'administrateur initial, enregistrer puis cliquer sur le bouton d'initialisation. L'action vérifie le compte YunoHost et refuse de remplacer un administrateur actif existant. Le premier connecté ne devient jamais administrateur automatiquement.

## Compatibilité et données

ARCenal Bridge reste seul propriétaire de l'appairage QSSE et des permissions de passerelle Dolibarr. La mise à jour conserve config.php à l'identique. Les paramètres privés VIGIlance sont séparés : un appairage Bridge ultérieur ne supprime pas l'accès à la base. Aucun secret de passerelle n'est demandé à l'installation.

SQLite et logos clients restent hors du dossier public. Le paquet comprend migrations, snapshots cohérents, sauvegarde/restauration et panneau d'amorçage administrateur. Les modules QSSE et identifiants historiques sont conservés ; leur portage autonome complet relève d'un prochain lot.

Les contrôles locaux et les doubles de helpers de cycle de vie passent. Lecture du branding par HTTPS borné avec vérification TLS, cache privé de cinq minutes et conservation du dernier logo valide en cas de panne. Installation, mise à jour, SSO et restauration YunoHost réels restent à réceptionner. Publier cette branche ne modifie pas automatiquement les références figées du catalogue ARCenal.

- [Code applicatif](https://github.com/arcenal-coder/arcenal-qsse)
- [Exploitation](docs/VIGILANCE_LOT01.md)
- [Rapport de tests locaux](docs/RECETTE_VIGILANCE_LOT01.md)
- [Intégration visuelle et rapport de contrôle](docs/VIGILANCE_LOT02_IDENTITE.md)
