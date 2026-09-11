# Passage de relais

Objectif : paquet ARCenal Portail autonome, appairé par ARCenal Bridge.

État : branche `codex/portal-standalone-v1`. Les scripts d’installation et de mise à jour ne modifient plus Dolibarr, sa permission ou la configuration de la passerelle. La migration supprime le panneau de configuration bêta devenu obsolète. Le portail affiche une page d’état claire tant que Bridge ne l’a pas appairé.

Vérifications à mener avant publication : validation du manifeste et des scripts, installation/mise à jour/suppression/restauration sur une instance YunoHost 12.1 de recette, puis test mobile SSO et remontée QSSE.
