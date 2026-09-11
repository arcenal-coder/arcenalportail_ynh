# ARCenal Portail pour YunoHost

[![Installer ARCenal Portail avec YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://onyx-ingenierie.com/yunohost/admin/#/apps/install-custom/https:%2F%2Fgithub.com%2Farcenal-coder%2Farcenalportail_ynh%2F)

*[Read this README in English.](./README.md)*

ARCenal Portail est l’espace mobile des équipiers : mise au travail, remontées terrain et suivi des actions PAO. L’authentification est assurée par le SSO/LDAP de YunoHost ; aucun équipier ne se connecte à Dolibarr.

## Installation

1. Installez ARCenal Portail et sélectionnez le groupe YunoHost des équipiers.
2. Dans Dolibarr, préparez l’appairage depuis **ARCenal QSSE > Configuration et connexions**.
3. Installez **ARCenal Bridge** depuis le catalogue ARCenal. Il sélectionne le portail et Dolibarr, puis utilise le code temporaire affiché par le module.

Le portail peut être mis à jour indépendamment : ses mises à jour ne créent ni ne modifient une permission Dolibarr.

## Sécurité

ARCenal Bridge est le seul composant qui gère la liaison technique. Il crée une permission YunoHost limitée au seul endpoint signé `/custom/arcenalqsse/gateway.php`. Les accès Dolibarr et le groupe Direction restent inchangés.

## Ressources

- [Module ARCenal QSSE](https://github.com/arcenal-coder/arcenal-qsse)
- [ARCenal Bridge](https://github.com/arcenal-coder/arcenalbridge_ynh)
- [Signaler un problème](https://github.com/arcenal-coder/arcenalportail_ynh/issues)
## Version 1.0.3

Le portail affiche la MTO QVT des cinq derniers jours ouvrés et indique si la MAT du jour a déjà été enregistrée. Une MAT négative déclenche une alerte Direction ; elle ne crée pas automatiquement une action PAO.
