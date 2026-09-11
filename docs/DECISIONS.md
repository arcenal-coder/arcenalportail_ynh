# Décisions

- OpenCode Desktop est l’outil quotidien; Codex intervient pour une revue délicate, un blocage persistant ou une décision d’architecture.
- OpenAI est utilisé par OAuth uniquement. Aucune clé API n’est stockée dans le projet.
- Les conversations ne sont pas partageables. Les instructions, agents et décisions restent versionnés dans Git.
- La compaction est automatique avec pruning des sorties d’outils; conserver les derniers tours est préférable à maintenir une session longue.
- La permission SSOwat de la passerelle appartient à l’application Dolibarr détectée depuis l’URL de la passerelle. Elle est limitée au chemin de la passerelle, cachée, protégée et ne modifie jamais la permission principale Dolibarr.
