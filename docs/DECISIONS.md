# Décisions

- ARCenal Portail reste une application SSO/LDAP autonome. Il ne crée ni ne modifie de permission d’un autre applicatif.
- ARCenal Bridge est seul propriétaire de l’appairage inter-applications et de la permission YunoHost limitée à la passerelle QSSE.
- L’appairage utilise un code à usage unique, valable vingt minutes. La clé HMAC durable n’est jamais demandée dans l’interface d’installation.
- Les mises à jour sont publiées depuis des versions immuables, après qualification YunoHost complète (installation, mise à jour, suppression, restauration).
- A MAT is a daily pre-work declaration. A negative result alerts the Dolibarr Direction group; PAO creation remains a qualified management decision.
- The portal only renders the five-business-day MTO QVT returned by QSSE. It stores no QSSE record locally.

## 2026-09-19 — Deux passerelles métier indépendantes

Le portail conserve la passerelle QSSE et utilise une seconde passerelle SIRH, chacune avec sa clé, son entité Dolibarr et sa permission YunoHost limitée à son unique endpoint. Cette séparation permet d’installer QSSE seul, SIRH seul ou les deux, sans partager de secret ni rendre le portail indisponible lorsqu’un fournisseur est absent.
