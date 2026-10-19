# VIGIlance — vidéo 1 Go et actualisation du logo

Candidate du 19 octobre 2026 : `1.1.0-beta4~ynh2`.

La vidéo est limitée à 1 073 741 824 octets (1 Go affiché), au lieu de 256 Mio. nginx et PHP acceptent une enveloppe de 1028 Mio ; chaque vidéo est plafonnée à 1024 Mio. PDF : 20 Mio ; QR et logo : 2 Mio, inchangés. Le navigateur refuse un dépassement avant transfert. Les erreurs PHP de taille et de transfert interrompu sont explicites ; un dépassement POST n’est plus présenté comme une expiration du formulaire.

L’import utilise des fichiers sans tampon mémoire de 1 Go. Prévoir environ trois fois la taille du média en espace temporaire, puis la place des anciennes révisions et sauvegardes. Traitement PHP : 300 secondes ; réception : 600 secondes ; attente FastCGI : 330 secondes. Un proxy externe peut imposer sa propre limite, à vérifier sur l’instance cliente.

Régression logo reproduite : un cache impossible à créer, un verrou occupé ou une écriture d’état échouée pouvaient être annoncés comme une actualisation réussie. Ces échecs sont signalés ; le dernier logo valide reste utilisable. Une source YunoHost non configurée est expliquée. La priorité du logo YunoHost sur le logo local de secours est inchangée ; un remplacement sous le même nom est testé. La source publique ONYX répond depuis le Mac. La cause spécifique du signalement sur l’instance cliente reste NON VÉRIFIÉE sans son message et son accès.

Contrôles locaux : syntaxe PHP/JavaScript/Bash/TOML, compilation PHP/Python, 94 assertions parcours, 38 contrôles navigateur de parcours, 43 branding, 59 services, 30 HTTP, 55 stockage, 22 unitaires, 27 contrôles de qualification et 19 assertions de cycle de vie avec helpers doublés. Revue indépendante favorable. Aucun transfert de 1 Go : bornes inclusives testées sans fichier massif et parcours HTTP réel avec médias synthétiques isolés.

La qualification native exige l’upgrade depuis beta4 ynh1 publiée (`cc0af029bb944761600bd960ae73eca2b6060ea6`) avant promotion sélective Preview. Aucun déploiement client par un push GitHub. Sauvegarde complète vérifiée avant mise à jour ; retour arrière par restauration complète code et données.
