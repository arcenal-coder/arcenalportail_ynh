# VIGIlance for YunoHost — preview candidate 1.1.0-beta.2

*[Lire ce README en français.](./README_fr.md)*

VIGIlance provides autonomous employee dossiers, personal access, HR management, team-scoped manager access and explicit permissions. YunoHost SSO authenticates accounts. Dolibarr is intended for future indicator dashboards and is not required for these features. The historical installed app id `arcenalportail` is preserved.

## Preview update

Candidate package: **1.1.0~beta2+ynh1**, branch **preview**.
Source URL: https://github.com/arcenal-coder/arcenalportail_ynh/tree/preview

This increment integrates the approved Gratitude styling into the real PHP application, automatically uses the logo declared on the main YunoHost domain, and anchors the publisher signature at the bottom right. The main domain is obtained through YunoHost's CLI; branding settings stay separate from Bridge. Existing profile switching remains server-validated, including without JavaScript. The real YunoHost group mapping and the candidate/onboarding workflows are subsequent increments.

Use a representative preview instance with a verified backup. Fresh installations explicitly select the initial administrator and company name. After upgrading an older installation, open the app's Configuration in YunoHost, select the initial administrator, save the choice and use the initialization button. The action verifies the YunoHost account and refuses to replace an existing active administrator. No first-login automatic elevation occurs.

## Compatibility and data

ARCenal Bridge remains the sole owner of QSSE pairing and Dolibarr gateway permissions. Upgrades preserve its config.php byte-for-byte. VIGIlance stores its private application settings separately, so later Bridge pairing cannot erase database access. No gateway key is requested by the installer.

SQLite and client logos stay outside the public web root. The package includes migrations, consistent snapshots, backup/restore and an initial-admin configuration panel. Existing QSSE modules and historical identifiers are retained; their full autonomous port is a later work item.

Local checks and lifecycle helper doubles passed. Branding uses bounded HTTPS reads with TLS checks, a private five-minute cache and the last valid logo on source failure. Real YunoHost installation, upgrade, SSO and restore still require acceptance. Publishing this branch does not itself update the pinned ARCenal catalog feeds.

- [Source application](https://github.com/arcenal-coder/arcenal-qsse)
- [Operations](docs/VIGILANCE_LOT01.md)
- [Local test report](docs/RECETTE_VIGILANCE_LOT01.md)
- [Identity integration and test report](docs/VIGILANCE_LOT02_IDENTITE.md)
