# ARCenal Portail pour YunoHost

[![Installer ARCenal Portail avec YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://onyx-ingenierie.com/yunohost/admin/#/apps/install-custom/https:%2F%2Fgithub.com%2Farcenal-coder%2Farcenalportail_ynh%2F)

*[Read this README in English.](./README.md)*

> Ce paquet installe le portail équipier QSSE ARCenal sur un serveur YunoHost.

## Présentation

ARCenal Portail permet aux équipiers d’effectuer leur MAT, de transmettre leurs remontées terrain et de contribuer aux actions PAO qui leur sont affectées. Le SSO/LDAP YunoHost authentifie l’utilisateur. Une passerelle HTTPS signée relie le portail au module ARCenal QSSE pour Dolibarr.

**Version incluse :** 0.4.8~ynh1

## Configuration

L’assistant demande l’URL HTTPS publique générée par ARCenal QSSE et sa clé de liaison de 64 caractères. Le module Dolibarr connecté reste un prérequis distinct.

## Documentation et ressources

- Dépôt du code applicatif : <https://github.com/arcenal-coder/arcenal-qsse>
- Signaler un problème de paquet : <https://github.com/arcenal-coder/arcenalportail_ynh/issues>
- Documentation de création des paquets YunoHost : <https://doc.yunohost.org/dev/packaging/>

## Informations pour les développeurs

```bash
sudo yunohost app install https://github.com/arcenal-coder/arcenalportail_ynh --debug
```
