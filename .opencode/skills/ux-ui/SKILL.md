---
name: ux-ui
description: Concevoir ou améliorer l’expérience et l’interface du portail ARCenal, pour les parcours utilisateurs, écrans, composants et retours mobiles. Ne pas utiliser pour de simples changements de packaging YunoHost sans impact d’interface.
compatibility: opencode
---

# UX/UI du portail ARCenal

Utilise ce skill lorsqu’une demande touche à l’interface du portail équipier : page, formulaire, tableau de bord, navigation, état vide, message d’erreur, composant ou parcours mobile.

## Cadrer avant de modifier

- Identifier l’utilisateur concerné (équipier, encadrant ou administrateur), son objectif et le contexte d’usage, notamment sur mobile et sur le terrain.
- Examiner l’écran et les composants existants avant de proposer une nouvelle structure. Respecter le vocabulaire métier déjà présent.
- Si le dépôt ne contient que le paquet YunoHost, préciser que l’interface applicative est probablement dans le dépôt amont `arcenal-qsse`; ne pas inventer d’écrans dans les fichiers de packaging.

## Concevoir l’écran ou le parcours

- Donner une priorité claire à l’action principale. Éviter les écrans qui demandent plusieurs choix équivalents avant que l’utilisateur comprenne sa tâche.
- Préférer des libellés explicites, des états visibles et des retours d’action utiles. Les erreurs indiquent quoi corriger et conservent la saisie lorsque c’est possible.
- Concevoir d’abord pour un écran étroit : actions atteignables, contenu lisible, tableaux transformés si nécessaire en cartes ou en vues détaillées.
- Préserver l’accessibilité : structure de titres cohérente, navigation clavier, focus visible, labels associés aux champs, messages d’erreur annoncés et contraste suffisant. Ne pas transmettre l’information uniquement par la couleur ou une icône.
- Réutiliser les composants, espacements et styles existants. Créer un nouveau composant seulement si son rôle est distinct et récurrent.

## Mettre en œuvre et vérifier

- Faire le plus petit changement qui améliore le parcours demandé.
- Préserver les comportements d’authentification YunoHost et les paramètres de passerelle; une amélioration visuelle ne doit jamais exposer `gateway_key` ni des données sensibles dans le navigateur.
- Vérifier les états pertinents : chargement, vide, erreur, succès, petit écran et clavier. Quand une interface locale peut être ouverte, vérifier le rendu réel avant de conclure.
- À la fin, résumer le parcours amélioré, les états vérifiés et toute limite restant à tester sur appareil réel.
