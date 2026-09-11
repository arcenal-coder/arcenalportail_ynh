# ARCenal Portail pour YunoHost

[![Installer ARCenal Portail avec YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://onyx-ingenierie.com/yunohost/admin/#/apps/install-custom/https:%2F%2Fgithub.com%2Farcenal-coder%2Farcenalportail_ynh%2F)

*[Read this README in English.](./README.md)*

> Ce paquet installe le portail équipier QSSE ARCenal sur un serveur YunoHost.

## Présentation

ARCenal Portail permet aux équipiers d’effectuer leur MAT, de transmettre leurs remontées terrain et de contribuer aux actions PAO qui leur sont affectées. Le SSO/LDAP YunoHost authentifie l’utilisateur. Une passerelle HTTPS signée relie le portail au module ARCenal QSSE pour Dolibarr.

**Version incluse :** 0.5.0~ynh1

## Configuration

L’assistant demande l’URL HTTPS publique générée par ARCenal QSSE et sa clé de liaison de 64 caractères. Lorsque Dolibarr est installé sur le même serveur YunoHost, l’installation crée automatiquement une permission protégée limitée à la passerelle signée. Elle ne modifie jamais les accès Dolibarr ni les droits du groupe Direction.

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

La permission technique est portée par l’application Dolibarr et non par le portail. Elle autorise seulement `/custom/arcenalqsse/gateway.php`, sans tuile YunoHost et avec la signature HMAC du module. Les autres écrans Dolibarr restent réservés à la Direction.
