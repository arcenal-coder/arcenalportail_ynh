# Décisions

- ARCenal Portail reste une application SSO/LDAP autonome. Il ne crée ni ne modifie de permission d’un autre applicatif.
- ARCenal Bridge est seul propriétaire de l’appairage inter-applications et de la permission YunoHost limitée à la passerelle QSSE.
- L’appairage utilise un code à usage unique, valable vingt minutes. La clé HMAC durable n’est jamais demandée dans l’interface d’installation.
- Les mises à jour sont publiées depuis des versions immuables, après qualification YunoHost complète (installation, mise à jour, suppression, restauration).
- Candidate VIGIlance lot 01 publiée sur testing à la demande du client pour permettre cette qualification ; elle ne vaut pas livraison qualifiée ni promotion de catalogue stable/preview. Révision Git publiée à identifier dans le passage de relais.
- VIGIlance porte désormais les dossiers et droits métier dans un stockage privé ; Dolibarr devient uniquement dashboard d'indicateurs. Les modules historiques sont conservés et le portage QSSE complet reste ultérieur.
- Version candidate 1.1.0~beta1+ynh1 choisie au-dessus des paquets Portail 1.0.x existants ; l'identifiant arcenalportail reste inchangé.
- Configuration métier dans application-settings.php, séparée du config.php que Bridge peut remplacer. Le portail ne gère jamais les permissions de passerelle et respecte le statut paired.
- Amorçage administrateur explicite via choix à l'installation, panneau YunoHost ou CLI ; aucune élévation du premier utilisateur SSO.
