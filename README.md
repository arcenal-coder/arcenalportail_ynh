# VIGIlance for YunoHost — preview candidate 1.1.0-beta5

*[Lire ce README en français.](./README_fr.md)*

VIGIlance provides autonomous employee dossiers, personal access, HR management, team-scoped manager access and explicit permissions. YunoHost SSO authenticates accounts. Dolibarr is intended for future indicator dashboards and is not required for these features. The historical installed app id `arcenalportail` is preserved.

## Preview update

Candidate package: **1.1.0-beta5~ynh1**, branch **preview**.

beta5 adds durable email notifications for candidate opening/submission, FORM S 13 publication/refusal/acceptance and completed initial onboarding. Administration configures recipient addresses and monitors/retries failures. Delivery uses the local YunoHost MTA under the app account, with an app-domain sender, generic messages and an HTTPS SSO entry link. See the [email contract and acceptance limits](docs/VIGILANCE_WORKFLOW_EMAILS.md). Native qualification must cover twelve scenarios and real delivery to an isolated local test mailbox before catalog promotion; publishing a candidate is not proof of qualification or client receipt.

ynh2 fix: videos up to 1 GiB, aligned nginx/PHP limits and browser size check. PDF: 20 MiB; QR and logo: 2 MiB. Logo refresh reports cache/lock failures instead of false success. Allow roughly three times the video size in temporary storage. See the [fix notes](docs/VIGILANCE_IMPORT_VIDEO_1GO.md).
Source URL: https://github.com/arcenal-coder/arcenalportail_ynh/tree/preview

This increment connects the approved demonstration interface to real candidate dossiers, versioned FORM S 13 proposals and mandatory onboarding. Refusal notifies management; acceptance of the current proposal grants the employee profile. Administrators import video, four PDF charters and a communication QR, create the quiz and decide whether each revision requires a repeat step. Progress, scores, evidence and internal notifications are persisted. Fresh installations contain no demonstration people or content.

The Gratitude theme, automatic main-domain logo and server-validated profile switch remain. Direct YunoHost group mapping, final candidate attachment rules and official FORM S 13 PDF generation are still incomplete. Email receipt and configuration must be accepted on the client instance. Read the [journey and publication notes](docs/VIGILANCE_BETA4.md) before upgrading.

Use a representative preview instance with a verified backup. Fresh installations explicitly select the initial administrator and company name. After upgrading an older installation, open the app's Configuration in YunoHost, select the initial administrator, save the choice and use the initialization button. The action verifies the YunoHost account and refuses to replace an existing active administrator. No first-login automatic elevation occurs.

## Compatibility and data

ARCenal Bridge remains the sole owner of QSSE pairing and Dolibarr gateway permissions. Upgrades preserve its config.php byte-for-byte. VIGIlance stores its private application settings separately, so later Bridge pairing cannot erase database access. No gateway key is requested by the installer.

SQLite, client logos and uploaded welcome media stay outside the public web root. The additive schema migration preserves existing dossiers. The package includes consistent snapshots, media backup/restore and an initial-admin configuration panel. Existing QSSE modules and historical identifiers are retained; their full autonomous port is a later work item.

Local checks and lifecycle helper doubles passed. Branding uses bounded HTTPS reads with TLS checks, a private five-minute cache and the last valid logo on source failure. Real YunoHost installation, upgrade, SSO and restore still require acceptance. Publishing this branch does not itself update the pinned ARCenal catalog feeds.

- [Source application](https://github.com/arcenal-coder/arcenal-qsse)
- [Operations](docs/VIGILANCE_LOT01.md)
- [Local test report](docs/RECETTE_VIGILANCE_LOT01.md)
- [Identity integration and test report](docs/VIGILANCE_LOT02_IDENTITE.md)
