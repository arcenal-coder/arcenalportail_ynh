# Décisions

- ARCenal Portail reste une application SSO/LDAP autonome. Il ne crée ni ne modifie de permission d’un autre applicatif.
- ARCenal Bridge est seul propriétaire de l’appairage inter-applications et de la permission YunoHost limitée à la passerelle QSSE.
- L’appairage utilise un code à usage unique, valable vingt minutes. La clé HMAC durable n’est jamais demandée dans l’interface d’installation.
- Les mises à jour sont publiées depuis des versions immuables, après qualification YunoHost complète (installation, mise à jour, suppression, restauration).
