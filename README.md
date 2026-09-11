# ARCenal Portal for YunoHost

[![Install ARCenal Portal with YunoHost](https://install-app.yunohost.org/install-with-yunohost.svg)](https://onyx-ingenierie.com/yunohost/admin/#/apps/install-custom/https:%2F%2Fgithub.com%2Farcenal-coder%2Farcenalportail_ynh%2F)

*[Lire ce README en français.](./README_fr.md)*

ARCenal Portal is the mobile employee space for work-readiness checks, field reports and PAO action follow-up. YunoHost SSO/LDAP authenticates employees; they never log into Dolibarr.

## Installation

1. Install ARCenal Portal and select the YunoHost employee group.
2. In Dolibarr, prepare pairing from **ARCenal QSSE > Configuration et connexions**.
3. Install **ARCenal Bridge** from the ARCenal catalog. It selects the portal and Dolibarr, then uses the temporary module code.

Portal upgrades are independent: they never create or change a Dolibarr permission.

## Security

ARCenal Bridge is the sole owner of the technical connection. It creates a YunoHost permission limited to the signed `/custom/arcenalqsse/gateway.php` endpoint. Dolibarr access and the Direction group remain unchanged.

## Resources

- [ARCenal QSSE module](https://github.com/arcenal-coder/arcenal-qsse)
- [ARCenal Bridge](https://github.com/arcenal-coder/arcenalbridge_ynh)
- [Report an issue](https://github.com/arcenal-coder/arcenalportail_ynh/issues)
