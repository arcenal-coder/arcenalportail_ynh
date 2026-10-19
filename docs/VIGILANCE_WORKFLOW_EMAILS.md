# Workflows — notifications e-mail

Lot client validé le 19 octobre 2026. Candidate locale `1.1.0-beta5~ynh1`, non publiée et non qualifiée nativement à ce stade. La dernière preview publiée reste beta4 ynh2.

## Transitions couvertes

| Événement validé | Destinataires |
|---|---|
| Ouverture du dossier par Administration | Compte candidat associé |
| Soumission du dossier | Administration |
| Publication d'une version du FORM S 13 | Compte candidat associé |
| Refus/demande de modification | Direction |
| Acceptation personnelle de la proposition courante | Administration et RH |
| Fin complète de l'accueil initial | Administration et RH |

Une notification par compte, même avec plusieurs profils destinataires. Les brouillons, tentatives rejetées, anciennes propositions et accueils incomplets ne créent pas d'e-mail de réussite. Les notifications internes existantes sont conservées. Les actualisations de démarches après l'accueil conservent leurs notifications internes ; ce lot n'ajoute pas implicitement d'e-mails à ces événements supplémentaires.

## Destinataires et confidentialité

Dans **Administration → Paramètres → Utilisateurs, rôles et permissions**, renseigner **E-mail de notification** pour Administration, Direction et RH. Le champ est modifiable par le seul profil Administration, sous contrôle CSRF et révision concurrente.

L'ouverture du dossier initialise cette adresse pour le candidat avec l'adresse saisie par Administration. Modifier le contact personnel dans le dossier candidat ne redirige pas les notifications : l'adresse approuvée demeure gérée dans les paramètres du compte. Aucune adresse n'est déduite d'un nom d'utilisateur ou d'un en-tête navigateur ; aucune ligne du CSV équipiers n'est importée.

La file mémorise un identifiant de notification, un événement et les profils destinataires, pas de copie de dossier. Les messages utilisent des textes fixes sans nom, rémunération, motif de refus, pièce jointe ou condition contractuelle. Le lien est l'entrée HTTPS de VIGIlance, sans identifiant de dossier ni jeton ; SSO et droits applicatifs restent requis pour consulter le contenu. Les droits du destinataire et son adresse sont revérifiés au traitement ; un compte désactivé ou ayant perdu tous les profils destinataires entraîne une annulation.

## File durable et transport

- Migration additive `003-journey-mail.sql` : adresse par compte et table d'envoi. Les anciennes notifications ne sont pas envoyées rétroactivement.
- Changement métier, notification interne et inscription en file dans une même transaction. Un échec annule les trois ; aucun transport réseau n'est exécuté dans cette transaction ni dans la requête web.
- Tâche toutes les cinq minutes, exécutée sous l'utilisateur système de l'application. Configuration par helpers YunoHost, ressource `system_user.allow_email=true`, expéditeur `app@domaine-applicatif` cohérent avec cette ressource (actualisé au changement de domaine), transport local `/usr/sbin/sendmail` sans shell et sans secret SMTP supplémentaire.
- `mail_from` et `public_url` sont stockés dans `application-settings.php`, séparés de Bridge. L'installateur renseigne expéditeur et lien ; le changement d'URL actualise le lien. En configuration locale sans ces valeurs, la file reste en attente et aucun transport n'est appelé.
- Verrou privé commun aux workers ; lot de 50 messages maximum. Pas de renvoi des entrées déjà acceptées par le MTA. Échec de transport : jusqu'à huit essais, délai exponentiel plafonné à une heure, puis statut d'échec visible. Adresse absente : attente sans consommer les essais.
- Administration voit les compteurs et peut relancer les seuls e-mails en échec après correction. Les diagnostics conservés sont des codes fixes, jamais les sorties du MTA ou des adresses. La commande CLI n'écrit que des compteurs.
- La sauvegarde cohérente conserve la file et ses statuts avec la base ; la tâche cron est sauvegardée/restaurée et supprimée avec l'instance. Aucune modification du service de messagerie global ni du domaine principal YunoHost.

« Envoyé » signifie **accepté par le MTA local**, pas reçu dans une boîte. Une interruption après acceptation SMTP mais avant enregistrement du statut peut produire un nouvel essai ; un Message-ID déterministe reste identique avec la même configuration. Une restauration d'une ancienne sauvegarde peut également reprendre des messages déjà acceptés depuis : aucune garantie d'envoi exactement une fois n'est revendiquée.

## Contrôles

Tests isolés : `php tests/journey-mail.php` (file, droits, destinataires, cumul de rôles, reprise/échec, absence d'adresse, cloisonnement, verrou, MIME et processus sendmail factice) et `php tests/journeys.php` (transitions réelles et rollback). Tests HTTP : édition d'adresse, suivi et relance avec CSRF. Cycle de vie avec helpers doublés : installation, upgrade, changement d'URL, sauvegarde/restauration et désinstallation de la tâche.

Tous les transports de tests capturent localement des données synthétiques ; aucun destinataire réel n'est contacté. Une future qualification native doit couvrir l'upgrade depuis beta4 ynh2 (`6af4b824abab2f52862406d4fa5c2155b439386a`), désormais exigé par le plan et son validateur. Livraison effective via la messagerie YunoHost et réception chez le client restent à vérifier sur une instance autorisée ; la note technique de la preview précédente ne qualifie pas cette candidate.

### Résultats locaux du lot

Syntaxe PHP/Bash/TOML et compilation PHP/Python réussies. 48 assertions e-mail (y compris processus MTA factice, MIME et équité de file), 103 parcours réels, 59 services, 43 branding, 34 HTTP, 28 tests des outils de qualification et 26 assertions de cycle de vie avec helpers doublés réussis. `git diff --check` réussi. Revue indépendante : blocage potentiel par cinquante destinataires sans adresse corrigé et testé ; avis favorable sur la correction. Le prochain contrôle natif vérifie cron rendu, identité expéditeur provisionnée, worker sous UID applicatif, acceptation du vrai MTA, réception Dovecot locale et absence de renvoi au second lancement. Le worker est lancé manuellement par ce contrôle : le passage effectif du planificateur cron et la réception cliente ne sont pas prouvés par cette vérification.

L'aperçu local existant a été conservé : snapshot privé `before-journey-mail.sqlite` avant migration additive, sans réinitialisation. Administration accessible sur `http://127.0.0.1:8771/?view=settings` (HTTP 200, champs de notification et suivi de file présents). Son transport reste non configuré, donc aucun envoi réel. Aucun serveur client modifié, commit/push ni promotion de catalogue effectués dans ce lot.
