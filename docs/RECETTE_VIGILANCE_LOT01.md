# Recette technique locale — VIGIlance lot 01

Date : 14 octobre 2026. Candidate `1.1.0-beta.1`, issue de `main` QSSE `716e5b1`. Version ajustée pour permettre la mise à jour des paquets 1.0.x déjà publiés. Statut : **contrôles locaux réussis ; recette YunoHost et réception client encore à faire**.

## Résultat fonctionnel

Le portail existant comporte un socle autonome SQLite, des dossiers RH et personnels, un annuaire contrôlé, un périmètre manager, une administration des rôles, des indicateurs Direction locaux et la configuration de marque client. Il ne requiert pas de connexion à Dolibarr pour ces fonctions. La connexion QSSE historique reste optionnelle et ne constitue pas le portage QSSE autonome.

## Contrôles réellement exécutés

| Commande / contrôle | Résultat final |
|---|---|
| Syntaxe PHP des fichiers suivis et nouveaux | 48 fichiers, aucun échec |
| Parsing TOML et syntaxe Python du build et tests | Réussis |
| `node --check tests/portal-browser.cjs` | Réussi |
| `bash -n` appliqué séparément aux scripts de cycle de vie et configuration | 7 scripts réussis |
| `php tests/portal.php` | 59 assertions réussies, base SQLite réelle temporaire |
| `python3 tests/portal-http.py --browser` | 26 assertions HTTP et 84 contrôles navigateur réussis |
| `python3 tests/portal-lifecycle.py` | 17 assertions réussies avec helpers doublés, dont configuration Bridge séparée et chemins réels de backup/restore |
| `php tests/unit.php` | 22 contrôles historiques QSSE réussis |
| `php tests/storage.php` | 55 contrôles historiques stockage QSSE réussis |
| `python3 scripts/package.py` | Paquets construits, contrôles de contenu réussis |
| `git diff --check` | Réussi |

Le navigateur utilisé pour le résultat final est Chrome déjà installé, piloté par Playwright ; aucune installation ou mise à jour de dépendance/navigateur n'a été exécutée. Les fixtures sont temporaires et fictives. Aucun serveur client ni aucune base de production n'a été modifié.

## Traçabilité des critères du CDC

| Critères | Preuve locale / limite |
|---|---|
| AC-01 | Services, routes et navigateur sans passerelle ; panne HTTPS optionnelle vers une adresse de boucle locale sans effet sur les dossiers |
| AC-02 | Identités non autorisées et en-têtes HTTP forgés refusés ; bootstrap explicitement configuré ; SSO YunoHost réel restant à vérifier |
| AC-03 | Création, mises à jour et relecture via nouvelles requêtes/connexions SQLite ; dates invalides refusées |
| AC-04 | Accès salarié propre dossier ; refus IDOR et profil non attribué ; annuaire projeté sans champs administratifs |
| AC-05 | Périmètre manager testé dans le service et l'HTML ; champs administratifs absents |
| AC-06 | Deux entreprises fictives ; accès direct, recherche et compteurs cloisonnés ; annuaire sans permission refusé |
| AC-07 | Admin, Direction, QSSE et Tuteur sans accès RH implicite ; profils actifs limités aux rôles attribués |
| AC-08 | Révocation relue sur un objet/service déjà utilisé ; maintien des références historiques sans rétablir l'accès |
| AC-09 | Révision périmée et resoumission du formulaire en conflit refusées ; premier enregistrement conservé ; association de compte unique |
| AC-10 | Panne forcée de l'audit annule la création ; refus sans audit de réussite ; audit sans copie des données professionnelles |
| AC-11 | CSRF absent refusé, contenu HTML échappé, champs protégés rejetés |
| AC-12 | Archivage conserve l'identité ; compte autorisé sans dossier ne reçoit aucun dossier de substitution |
| AC-13 | Scripts réels exécutés avec doubles de helpers : installation, upgrade, snapshot et restauration avec données/configuration conservées ; cycle YunoHost réel NON EXÉCUTÉ |
| AC-14 | 77 contrôles historiques QSSE/stockage, écran MAT conservé avec indisponibilité honnête, secret absent du HTML ; workflows connectés et cycle Dolibarr NON REJOUÉS |
| AC-15 | RH, manager, salarié et admin à 360/768/1440 px, sans débordement du document ; tabulation, focus visible, labels et création RH vérifiés ; audit d'accessibilité humain complet non réalisé |

## Revue indépendante

Deux défauts importants ont été démontrés et corrigés :

1. En cas de conflit, le formulaire conservait des valeurs obsolètes avec une nouvelle révision. Il conserve désormais la révision soumise et demande un rechargement explicite ; la resoumission reste refusée.
2. La révocation d'un compte ou du rôle manager bloquait une modification RH de son dossier. Les références historiques inchangées sont désormais conservées ; une nouvelle association reste soumise aux contrôles de compte/rôle.

La revue de suivi a confirmé les corrections et n'a trouvé aucun autre défaut important démontré dans son périmètre. Les scénarios de régression font partie des tests locaux ci-dessus.

## Paquets et réserves de livraison

Portail : `dist/arcenalportail_ynh-1.1.0-beta.1.zip`, SHA-256 `23d1bbeacc110a16b2ab0114ae6f371484017fb2adb9f6eac1a425b011103ab2`.

Module QSSE historique : `dist/module_arcenalqsse-0.4.15.zip`, SHA-256 `1d961ee50ecb961cd0b3d6cd0a5fb57d0d2f193a9a920617e2f2ebabf81a4299`, identique à la baseline.

Avant livraison cliente : identifier le paquet installé, vérifier SSO et permissions système, contrôler les dépendances PHP déclarées, exécuter le cycle YunoHost réel sur copie représentative et recevoir les parcours par rôle. Docker local reste inaccessible ; aucune nouvelle recette GitHub n'a été lancée ou publiée. La sauvegarde avec helpers doublés ne vaut pas preuve de restauration de l'infrastructure cliente.

La V1 entière (coffre-fort/paie, onboarding, planning autonome, portage QSSE, export indicateurs Dolibarr) reste à réaliser dans les lots suivants. La candidate du lot 01 n'est pas déclarée livrée en production.
