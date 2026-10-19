# VIGIlance beta4 — publication Preview

Source applicative : `arcenal-coder/arcenal-qsse`, branche historique `preview/vigilance-lot01`, commit `624f6368e3e1598cbc79a14ff526e80f7de4264f`.

Paquet : `arcenal-coder/arcenalportail_ynh`, branche installable `preview`, version `1.1.0-beta4~ynh1`, identifiant installé conservé `arcenalportail`.

## Changements

Interface et parcours de la démonstration approuvée avec stockage réel : candidature personnelle, propositions FORM S 13 figées et versionnées, refus notifié à la Direction, nouvelle proposition puis acceptation obligatoire avant passage Équipier. Accueil serveur avec vidéo complète, quiz 26/30, quatre PDF, attestation, lien/QR de communication, Administration des contenus et reprise Oui/Non. Les notifications sont internes à l’application.

Une nouvelle installation démarre sans personnes, vidéos, documents ou questions fictives. Aucune ligne du CSV équipiers n’est importée. Les téléchargements PDF/vidéo nécessitent une identité applicative autorisée ; le stockage est hors racine web.

## Mise à jour et retour arrière

Prévoir une sauvegarde YunoHost vérifiée avant mise à jour. La migration `002-journeys.sql` est additive et garde l’empreinte de la migration initiale. Elle est appliquée par le cycle de mise à jour, jamais par une requête web. Les médias privés `welcome/` sont inclus dans les scripts de sauvegarde et restauration. Limites d’import : vidéo MP4/WebM 256 Mo, PDF 20 Mo, QR 2 Mo.

Le retour à beta3 exige une restauration de sa sauvegarde complète, code et base ensemble ; le code beta3 refuse correctement une base portant un schéma plus récent. Ne pas effectuer un simple retour de branche en gardant la base beta4.

## Preuves et limites

Contrôles locaux réussis : 71 assertions nouveaux parcours, 36 parcours navigateur, 59 services du socle, 29 HTTP, 125 navigateur du socle, 38 branding, 55 stockage, 22 unitaires historiques, 19 cycle de vie avec helpers doublés et 26 tests des outils de qualification. Les vidéos à durée fractionnaire sont couvertes par une régression serveur et navigateur. Revue indépendante favorable après correction de l’arrondi vidéo. La qualification native exige désormais aussi l’upgrade depuis beta3 `39eaa3c`, en plus des origines beta1/beta2. Ces contrôles locaux ne remplacent pas la qualification native du commit paquet ni la recette cliente.

Envoi des e-mails, projection des groupes YunoHost, règles finales des pièces candidates et génération du PDF officiel FORM S 13 restent à intégrer. Les propositions et leur acceptation sont déjà conservées en ligne. Aucun résultat de qualification beta3 n’est transféré à beta4.
