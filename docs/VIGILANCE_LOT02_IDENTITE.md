# VIGIlance — lot 02, intégration de l'identité visuelle

Candidate : `1.1.0~beta2+ynh1`, archive `arcenalportail_ynh-1.1.0-beta.2.zip`.
SHA-256 de l'archive locale : `44796ed204ba47c267a85138c80c7ac9dc4be0b4405d587cd926af02fa1fb085`.
Statut : contrôles locaux réussis ; réception du rendu et recette YunoHost réelle à effectuer par le client.

## Résultat de cet incrément

- Thème Gratitude intégré aux écrans PHP réels : fond crème/vert léger, accent terracotta, surfaces claires et typographie sans serif.
- Logo entreprise simple en haut à gauche, identité VIGIlance et signature « Propulsé par ARCenal OS » ancrée en bas à droite. La signature n'intercepte pas les interactions avec la page.
- Cadre commun extrait dans `views/shell-start.php` et `shell-end.php`. Les formulaires et services existants gardent leurs contrôles serveur.
- Sélecteur de profil actuel amélioré par JavaScript externe, avec POST et CSRF côté serveur et bouton de secours sans JavaScript.
- Identité commune également appliquée aux pages QSSE historiques, dont les processus restent conservés.

Référence visuelle : thème Gratitude du dépôt gratitude_ynh, commit `62509428d62225d024bebcd5d290ce36e41a1428`, examiné et présenté en maquette avant intégration.

## Logo automatique et source principale

Constat public : le logo ONYX est configuré sur le domaine principal YunoHost, mais pas sur le sous-domaine sirh. Le packaging détermine donc le domaine principal avec `yunohost domain main-domain --output-as json` au lieu de supposer que le domaine de l'application porte le logo.

L'origine est sauvegardée sous `branding_origin` dans `application-settings.php`. Bridge conserve la propriété de `config.php` et ne reçoit aucun droit supplémentaire. Aucun domaine ONYX n'est codé en dur dans le portail ou les scripts d'installation.

Le service `Branding` lit exclusivement les routes publiques fixes `/yunohost/portalapi/public` et `/yunohost/sso/customassets/<fichier>` sur cette origine :

- HTTPS avec vérification TLS et sans redirection, authentifiants ou cookies ; origine non choisie par une requête du navigateur.
- Métadonnées limitées à 64 Kio, image à 2 Mio et dimensions à 4096 px ; PNG, JPEG ou WebP uniquement.
- Nom de fichier distant contrôlé, type de média vérifié, empreinte des octets et écriture atomique dans un cache privé.
- Vérification périodique toutes les cinq minutes, verrou non bloquant pour éviter des téléchargements simultanés.
- En cas d'échec, dernier logo validé, logo de secours existant ou nom d'entreprise ; aucun échec de branding ne bloque un dossier.
- « Actualiser depuis YunoHost » force une vérification du média et nécessite le profil Administration et un jeton CSRF.

Le cache sous `$data_dir/branding` est une donnée dérivée régénérable, pas une preuve RH. Les paramètres privés sont inclus avec les fichiers de l'installation ; base et logos manuels continuent d'utiliser la sauvegarde existante. Aucun changement de schéma ou migration de données n'est introduit par ce lot.

## Contrôles locaux réellement exécutés

| Contrôle | Résultat |
|---|---|
| Syntaxe PHP, Bash, Python, TOML et JavaScript | Réussie ; détails finaux dans l'état ARCenal |
| `php tests/branding.php` | 38 assertions : cache, refus d'origines/références invalides, taille/type/dimensions, panne et repli, réparation du cache et suppression explicite du logo source |
| `php tests/portal.php` | 59 assertions du socle autonome réussies |
| `python3 tests/portal-http.py --browser` | 29 assertions HTTP et 125 contrôles navigateur réussis à 360/768/1440 px |
| `python3 tests/portal-lifecycle.py` | 18 assertions réussies avec helpers doublés ; domaine principal distinct du sous-domaine applicatif |
| Historique QSSE / stockage | 22 + 55 contrôles réussis |
| Build du paquet et `git diff --check` | Réussis |

Une exécution supplémentaire utilise réellement l'origine publique YunoHost ONYX pour la lecture du logo et produit les captures `docs/apercus-gratitude-lot02/` ; comptes, dossiers et serveur applicatif de ces captures sont des fixtures locales fictives. Cette lecture publique ne modifie pas le serveur client.

Les contrôles vérifient aussi le retour Administration → Équipier → Administration avec un même compte fictif, le refus de l'administration sous le profil Équipier et la soumission du profil sans JavaScript. Il s'agit des droits applicatifs existants ; la projection réelle des groupes YunoHost dans les profils métiers reste le prochain incrément.

## Prochain point de contrôle

Recevoir le rendu intégré, puis poursuivre la correspondance des groupes YunoHost et profils et les parcours candidat/validation d'intérêt/accueil définis en réunion. Les KPI, invitations et parcours de la maquette ne sont pas présentés comme déjà intégrés dans ce lot visuel.

La qualification complète installation/mise à jour/SSO/restauration YunoHost sur l'environnement client reste à réaliser. La candidate demeure une publication preview de recette au niveau 0.

Une revue indépendante bornée du code et du paquet a été effectuée avant publication ; aucun défaut important démontré n'a été relevé. Les limites de qualification réelles ci-dessus restent applicables.
