# Règles du projet

- Paquet YunoHost (format 2) pour le portail équipier ARCenal QSSE. Préserver les conventions YunoHost et la compatibilité YunoHost >= 12.1.
- Avant toute modification, lire `manifest.toml`, le script concerné dans `scripts/` et les fichiers de configuration liés dans `conf/`.
- Garder les changements ciblés. Ne jamais introduire de secret réel : `gateway_key` est sensible et les valeurs de `tests.toml` sont fictives.
- Valider les scripts shell modifiés avec `bash -n`; valider les fichiers TOML modifiés avec l’outil disponible. Ne simuler aucune installation YunoHost locale sans le demander.
- Garder les README anglais et français cohérents lorsqu’un comportement utilisateur change.
- Utiliser `@explorateur` pour localiser un impact dans le paquet et `@controleur` pour relire un diff. Noter les décisions durables dans `docs/DECISIONS.md`.
- Une session par objectif. À la fin, compléter `docs/HANDOFF.md` avec le résultat, les vérifications et la prochaine étape.
- Ne jamais partager une session, ne jamais ajouter de fournisseur ou de clé API sans accord explicite. Utiliser Codex comme expert en cas de problème d’architecture, de sécurité ou de packaging persistant.
