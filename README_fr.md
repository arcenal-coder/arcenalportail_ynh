# ARCenal Portail pour YunoHost

[![Installer ARCenal Portail avec YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://onyx-ingenierie.com/yunohost/admin/#/apps/install-custom/https:%2F%2Fgithub.com%2Farcenal-coder%2Farcenalportail_ynh%2F)

*[Read this README in English.](./README.md)*

> Ce paquet installe le portail équipier QSSE ARCenal sur un serveur YunoHost.

## Présentation

ARCenal Portail permet aux équipiers d’effectuer leur MAT, de transmettre leurs remontées terrain et de contribuer aux actions PAO qui leur sont affectées. Le SSO/LDAP YunoHost authentifie l’utilisateur. Une passerelle HTTPS signée relie le portail au module ARCenal QSSE pour Dolibarr.

**Version incluse :** 0.4.12~ynh1

## Configuration

L’assistant demande l’URL HTTPS publique générée par ARCenal QSSE et sa clé de liaison de 64 caractères. Le module Dolibarr connecté reste un prérequis distinct.

Si l’administration web YunoHost n’affiche pas de panneau de configuration, relancer la liaison depuis le serveur avec :

```bash
sudo /etc/yunohost/apps/arcenalportail/scripts/link_qsse arcenalportail
```

## Documentation et ressources

- Dépôt du code applicatif : <https://github.com/arcenal-coder/arcenal-qsse>
- Signaler un problème de paquet : <https://github.com/arcenal-coder/arcenalportail_ynh/issues>
- Documentation de création des paquets YunoHost : <https://doc.yunohost.org/dev/packaging/>

## Informations pour les développeurs

```bash
sudo yunohost app install https://github.com/arcenal-coder/arcenalportail_ynh --debug
```

## Liaison QSSE Dolibarr

À partir de `0.4.14~ynh1`, la mise à jour du portail restaure automatiquement la permission technique qui permet au portail d’appeler la passerelle Dolibarr QSSE. Cette permission donne accès uniquement à l’URL de passerelle configurée, sans afficher de tuile YunoHost.

Si le portail affiche “La liaison QSSE est momentanément indisponible”, vérifiez d’abord que l’application installée est au moins en `0.4.14~ynh1`, puis relancez la mise à jour depuis le catalogue ARCenal.
