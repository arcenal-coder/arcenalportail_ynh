# Décisions

- 19 octobre 2026 — ynh2 porte la vidéo à 1 Gio, PHP à 1024M et l’enveloppe POST/nginx à 1028M, sans augmenter la mémoire PHP. Délais finis pour traitement/copie. PDF et QR inchangés. Les échecs de cache logo sont signalés, aucune priorité de source changée. Qualification native exigée depuis beta4 publiée sans exception de permissions historiques pour cette version.

- 19 octobre 2026 — beta4 reprend les parcours de la démonstration avec stockage réel. L’état initial est vide ; médias et quiz se configurent dans l’Administration. Le refus d’un FORM S 13 notifie la Direction et ne fait pas passer Équipier ; une nouvelle proposition doit être acceptée.
- Les migrations sont additives et les fichiers d’accueil privés sont sauvegardés. Un retour beta3 nécessite la restauration de la base et du code beta3 ensemble. Les e-mails et la synchronisation des groupes restent des intégrations distinctes.

- ARCenal Portail reste une application SSO/LDAP autonome. Il ne crée ni ne modifie de permission d’un autre applicatif.
- ARCenal Bridge est seul propriétaire de l’appairage inter-applications et de la permission YunoHost limitée à la passerelle QSSE.
- L’appairage utilise un code à usage unique, valable vingt minutes. La clé HMAC durable n’est jamais demandée dans l’interface d’installation.
- Les mises à jour sont publiées depuis des versions immuables, après qualification YunoHost complète (installation, mise à jour, suppression, restauration).
- Candidate VIGIlance lot 01 publiée sur testing à la demande du client pour permettre cette qualification ; elle ne vaut pas livraison qualifiée ni promotion de catalogue stable/preview. Révision Git publiée à identifier dans le passage de relais.
- VIGIlance porte désormais les dossiers et droits métier dans un stockage privé ; Dolibarr devient uniquement dashboard d'indicateurs. Les modules historiques sont conservés et le portage QSSE complet reste ultérieur.
- Version candidate 1.1.0~beta1+ynh1 choisie au-dessus des paquets Portail 1.0.x existants ; l'identifiant arcenalportail reste inchangé.
- Configuration métier dans application-settings.php, séparée du config.php que Bridge peut remplacer. Le portail ne gère jamais les permissions de passerelle et respecte le statut paired.
- Amorçage administrateur explicite via choix à l'installation, panneau YunoHost ou CLI ; aucune élévation du premier utilisateur SSO.
- Incrément 1.1.0~beta2+ynh1 : intégrer d'abord le rendu Gratitude validé, le logo automatique du domaine principal YunoHost et la signature stable, sans présenter les parcours métier simulés en maquette comme déjà intégrés.
- Le domaine du portail peut n'avoir aucun logo propre ; le domaine principal est obtenu par la CLI YunoHost et conservé sous branding_origin dans application-settings.php. La lecture publique du branding est bornée, sans redirection/authentifiants/cookies, avec cache privé et repli non bloquant.
