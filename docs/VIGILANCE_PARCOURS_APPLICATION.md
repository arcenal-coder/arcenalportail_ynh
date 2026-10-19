# Parcours réels issus de la démonstration approuvée

La maquette `docs/maquette-vigilance` est la référence visuelle. L'application démarre sans dossiers, médias ou questions de démonstration. Les données existantes restent conservées.

| Étape | Acteur et déclencheur | Écran et informations | Action et preuve | Notification et sortie |
|---|---|---|---|---|
| Ouverture candidature | Administration ; compte SSO déjà observé | Candidatures ; identité/contact du candidat | Créer un dossier lié au compte ; audit | Notification personnelle ; dossier à compléter |
| Dossier | Candidat ; dossier associé | Mon dossier ; uniquement ses propres données | Brouillon ou soumission ; version et date | Administration ; dossier soumis |
| Proposition | Administration ou Direction ; dossier soumis/refusé | FORM S 13 ; conditions du seul dossier autorisé | Publier une nouvelle version figée | Candidat ; validation à accepter |
| Refus/modification | Candidat ; proposition courante | Sa proposition uniquement | Refuser/demander une modification avec motif ; historique | Direction ; demeure candidat jusqu'à acceptation d'une nouvelle version |
| Acceptation | Candidat ; proposition courante | Conditions, confirmation personnelle | Acceptation datée ; rôle Équipier et statut en intégration | Administration/RH ; accueil obligatoire |
| Contenus | Administration ; préparation ou révision | Contenus d'accueil ; fichiers et éditeur de quiz | Import vidéo/PDF, questions/réponses/points ; publication versionnée avec reprise Oui/Non | Obligations ciblées ; preuves antérieures conservées |
| Accueil | Équipier ; accueil initial ou reprise | Bienvenue, vidéo, REX, quiz, quatre PDF, attestation, communication | Progression serveur, score serveur, versions et attestation datées | Administration/RH ; accès Équipier débloqué après fin complète |
| Suivi | Administration/RH/QSSE | KPI et progression ; aucune donnée de contractualisation pour QSSE | Consultation limitée au suivi d'accueil | Aucune mutation du dossier |
| Autres profils | Manager/Direction/RP/CRP/tuteur | Accès du socle selon leurs droits | Aucun droit RH implicite ni contournement d'accueil depuis Équipier | Profils cumulables ; révocation contrôlée à chaque requête |

## Configuration décidée le 19 octobre 2026

Vidéo et quatre emplacements PDF prévus dans l'application : ajouter/importer, stockage privé, remplacement versionné. Le quiz et son corrigé sont créés et modifiés par l'Administration. Leur contenu n'est donc plus un prérequis à demander au client. Le CSV équipiers sert uniquement à définir les champs ; aucune ligne n'est importée. La liste définitive des pièces obligatoires reste à fournir ; elle ne bloque pas le socle de candidature.

Le refus d'un FORM S 13 avertit la Direction. Après échange, une nouvelle proposition peut être publiée puis acceptée personnellement. Le refus ne donne aucun rôle Équipier.

## Suite validée : e-mails des workflows

Candidate locale beta5 : la file persistante ajoute les e-mails aux notifications internes des six transitions ouverture, soumission, proposition, refus, acceptation et fin d'accueil. Adresses gérées par Administration, reprise des échecs et transport local YunoHost. Voir [le contrat et les limites de livraison](VIGILANCE_WORKFLOW_EMAILS.md). Ce lot local ne constitue pas une publication ni une preuve de réception chez le client.
