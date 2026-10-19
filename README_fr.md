# VIGIlance pour YunoHost — candidate preview 1.1.0-beta5

*[Read this README in English.](./README.md)*

VIGIlance propose dossiers collaborateurs autonomes, accès personnel, gestion RH, périmètres manager et permissions explicites. YunoHost SSO authentifie les comptes. Dolibarr est destiné aux dashboards d'indicateurs futurs et n'est pas requis pour ces fonctions. L'identifiant installé historique `arcenalportail` est conservé.

## Mise à jour preview

Paquet candidat : **1.1.0-beta5~ynh1**, branche **preview**.

beta5 ajoute les e-mails persistants d'ouverture/soumission de candidature, publication/refus/acceptation du FORM S 13 et fin d'accueil initial. Administration règle les adresses et suit/relance les échecs. Le MTA local YunoHost est utilisé sous le compte applicatif, avec expéditeur sur le domaine de l'application, messages génériques et lien HTTPS avec SSO. Voir [le contrat e-mail et les limites de recette](docs/VIGILANCE_WORKFLOW_EMAILS.md). La qualification native doit couvrir douze scénarios et une livraison réelle à une boîte locale isolée avant promotion catalogue ; publier une candidate ne prouve pas sa qualification ni la réception cliente.

Correctif ynh2 : vidéo jusqu’à 1 Go, limites nginx/PHP alignées, contrôle avant transfert. PDF : 20 Mo ; QR et logo : 2 Mo. Les échecs de cache ou de verrou lors de l’actualisation du logo sont signalés sans faux succès. Prévoir environ trois fois la taille vidéo en espace temporaire. Voir les [notes du correctif](docs/VIGILANCE_IMPORT_VIDEO_1GO.md).
URL source : https://github.com/arcenal-coder/arcenalportail_ynh/tree/preview

Cet incrément raccorde l’interface de démonstration approuvée aux dossiers candidats réels, aux propositions FORM S 13 versionnées et à l’accueil obligatoire. Un refus notifie la Direction ; l’acceptation de la proposition courante ouvre le profil Équipier. L’Administration importe vidéo, quatre chartes PDF et QR de communication, crée le quiz et décide si chaque révision impose une nouvelle démarche. Progression, scores, preuves et notifications internes sont persistés. L’installation neuve ne contient aucune personne ou contenu de démonstration.

Le thème Gratitude, le logo automatique du domaine principal et le sélecteur de profil contrôlé côté serveur sont conservés. La correspondance des groupes YunoHost, les règles finales des pièces candidates et la génération du PDF officiel FORM S 13 restent à compléter. Configuration et réception des e-mails restent à réceptionner sur l'instance cliente. Lire les [notes de parcours et de publication](docs/VIGILANCE_BETA4.md) avant mise à jour.

Utiliser une instance preview représentative et une sauvegarde vérifiée. Une installation neuve choisit explicitement l'administrateur initial et l'entreprise. Après mise à jour d'une ancienne installation, ouvrir Configuration dans l'application YunoHost, sélectionner l'administrateur initial, enregistrer puis cliquer sur le bouton d'initialisation. L'action vérifie le compte YunoHost et refuse de remplacer un administrateur actif existant. Le premier connecté ne devient jamais administrateur automatiquement.

## Compatibilité et données

ARCenal Bridge reste seul propriétaire de l'appairage QSSE et des permissions de passerelle Dolibarr. La mise à jour conserve config.php à l'identique. Les paramètres privés VIGIlance sont séparés : un appairage Bridge ultérieur ne supprime pas l'accès à la base. Aucun secret de passerelle n'est demandé à l'installation.

SQLite, logos clients et médias d’accueil restent hors du dossier public. La migration additive conserve les dossiers existants. Le paquet comprend snapshots cohérents, sauvegarde/restauration des médias et panneau d’amorçage administrateur. Les modules QSSE et identifiants historiques sont conservés ; leur portage autonome complet relève d’un prochain lot.

Les contrôles locaux et les doubles de helpers de cycle de vie passent. Lecture du branding par HTTPS borné avec vérification TLS, cache privé de cinq minutes et conservation du dernier logo valide en cas de panne. Installation, mise à jour, SSO et restauration YunoHost réels restent à réceptionner. Publier cette branche ne modifie pas automatiquement les références figées du catalogue ARCenal.

- [Code applicatif](https://github.com/arcenal-coder/arcenal-qsse)
- [Exploitation](docs/VIGILANCE_LOT01.md)
- [Rapport de tests locaux](docs/RECETTE_VIGILANCE_LOT01.md)
- [Intégration visuelle et rapport de contrôle](docs/VIGILANCE_LOT02_IDENTITE.md)
