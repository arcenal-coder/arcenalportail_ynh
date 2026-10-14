# VIGIlance for YunoHost — preview candidate 1.1.0-beta.1

*[Lire ce README en français.](./README_fr.md)*

VIGIlance provides autonomous employee dossiers, personal access, HR management, team-scoped manager access and explicit permissions. YunoHost SSO authenticates accounts. Dolibarr is intended for future indicator dashboards and is not required for these features. The historical installed app id `arcenalportail` is preserved.

## Preview update

Candidate package: **1.1.0~beta1+ynh1**, branch **testing**.
Source URL: https://github.com/arcenal-coder/arcenalportail_ynh/tree/testing

Use a representative preview instance with a verified backup. Fresh installations explicitly select the initial administrator and company name. After upgrading an older installation, open the app's Configuration in YunoHost, select the initial administrator, save the choice and use the initialization button. The action verifies the YunoHost account and refuses to replace an existing active administrator. No first-login automatic elevation occurs.

## Compatibility and data

ARCenal Bridge remains the sole owner of QSSE pairing and Dolibarr gateway permissions. Upgrades preserve its config.php byte-for-byte. VIGIlance stores its private application settings separately, so later Bridge pairing cannot erase database access. No gateway key is requested by the installer.

SQLite and client logos stay outside the public web root. The package includes migrations, consistent snapshots, backup/restore and an initial-admin configuration panel. Existing QSSE modules and historical identifiers are retained; their full autonomous port is a later work item.

Local checks and lifecycle helper doubles passed. Real YunoHost installation, upgrade, SSO and restore still require acceptance. Publishing testing does not promote the pinned ARCenal preview/stable catalog feeds.

- [Source application](https://github.com/arcenal-coder/arcenal-qsse)
- [Operations](docs/VIGILANCE_LOT01.md)
- [Local test report](docs/RECETTE_VIGILANCE_LOT01.md)
