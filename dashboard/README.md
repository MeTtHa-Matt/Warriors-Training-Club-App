# JCM Studio

Tableau de bord PHP autonome. Le dossier `dashboard/` peut devenir a lui seul la racine web d'un domaine distinct : il ne charge aucun fichier du site principal et ne partage pas sa session. La console propose aussi les outils d'administration du site ; son identifiant de connexion est independant des comptes membres.

Le dashboard est installable comme PWA sur Android et iOS. Le domaine doit etre servi en HTTPS (exception : `localhost` en developpement). Sur Android, ouvrir le domaine dans Chrome puis choisir **Installer l'application** ou **Ajouter a l'ecran d'accueil**. Sur iPhone/iPad, ouvrir le domaine dans Safari, toucher **Partager**, puis **Sur l'ecran d'accueil**. Le service worker conserve les ressources visuelles et une page hors connexion ; les pages privees et les donnees restent demandees au serveur et ne sont jamais mises en cache.

## Configuration

1. Installer les dependances dans ce dossier avec `composer install --no-dev --optimize-autoloader`.
2. Copier `.env.example` en `.env` dans ce dossier et definir `DASHBOARD_USERNAME` ainsi qu'un `DASHBOARD_PASSWORD_HASH`. Generer le hash avec `php -r 'echo password_hash("un-mot-de-passe-long", PASSWORD_DEFAULT), PHP_EOL;'`. Le dashboard refuse les mots de passe stockes en clair. En production, garder `DASHBOARD_COOKIE_SECURE=1`.
3. Creer les tables admin avec `dashboard/admin-upgrade.sql` (les memes tables sont declarees dans le `db.sql` du site) puis creer un compte SQL dedie et renseigner ses acces `DB_*`. Les droits doivent couvrir lecture analytics, gestion des comptes, liens, signalements, commits et parametres. Exemple a adapter :

	```sql
	CREATE USER 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD' IDENTIFIED BY 'mot-de-passe-aleatoire-long';
	GRANT SELECT, UPDATE, DELETE ON warriors_training_club.account_wtc TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.seances TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.inscriptions_seances TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.ranking_records TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.signalements_wtc TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.analytics_events TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT ON warriors_training_club.sql_action_logs TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT, INSERT, UPDATE ON warriors_training_club.index_links TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT, INSERT, UPDATE, DELETE ON warriors_training_club.dashboard_reports TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT, INSERT, UPDATE, DELETE ON warriors_training_club.dashboard_commits TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	GRANT SELECT, INSERT, UPDATE ON warriors_training_club.app_settings TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	```

	Remplacer le nom de base et l'hote, utiliser le vrai mot de passe uniquement dans MySQL et dans `.env`, et restreindre l'hote a l'adresse du serveur du dashboard.
4. Pour une nouvelle base, importer `db.sql` du site. Pour une base qui possede deja la table analytics, appliquer une seule fois la migration :

	```sh
	mysql -u administrateur -p warriors_training_club < dashboard/analytics-upgrade.sql
	```

	Apres la creation ou la migration, appliquer aussi `dashboard/admin-upgrade.sql` et les droits admin ci-dessus. Pour le compte dashboard, l'acces a `analytics_events` est deja compris dans la liste :

	```sql
	GRANT SELECT ON warriors_training_club.analytics_events TO 'dashboard_admin'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	```

5. Configurer les variables `MAIL_*` pour l'envoi collectif SMTP. `GITHUB_TOKEN` est facultatif et augmente le quota de l'API GitHub.
6. Avant de deplacer le dossier hors du site, importer les fichiers JSON existants dans la base partagee :

	```sh
	php dashboard/bin/import-admin-data.php
	```

	Cette commande CLI est repetable, ne supprime pas les fichiers sources et doit etre lancee tant que `dashboard/` se trouve dans le dossier du site. Les nouveaux signalements et les commits du webhook sont ensuite synchronises dans les tables partagees.
7. Configurer `ANALYTICS_MAINTENANCE_DB_*` dans `.env` avec un compte SQL distinct disposant uniquement de `DELETE` sur `analytics_events`. Exemple :

	```sql
	CREATE USER 'analytics_maintenance'@'ADRESSE_DU_SERVEUR_DASHBOARD' IDENTIFIED BY 'secret-aleatoire-distinct';
	GRANT DELETE ON warriors_training_club.analytics_events TO 'analytics_maintenance'@'ADRESSE_DU_SERVEUR_DASHBOARD';
	```

8. Programmer le nettoyage de retention une fois par jour. La commande est CLI uniquement et supprime au plus 100 000 lignes par execution, en lots de 5 000 :

	```cron
	15 3 * * * /usr/bin/php /chemin/absolu/Warriors-Training-Club-App/dashboard/bin/cleanup-analytics.php
	```

9. Servir ce dossier exclusivement en HTTPS. Le rapport et les indicateurs du club sont caches 60 secondes dans un repertoire prive du systeme, hors racine web.

`DB_HOST` et `ANALYTICS_MAINTENANCE_DB_HOST` sont resolus depuis le serveur du dashboard. Utiliser `127.0.0.1` uniquement si MariaDB ecoute sur la meme machine ; sinon configurer l'adresse privee du serveur SQL, autoriser les connexions reseau uniquement depuis le dashboard et verifier les droits MySQL pour son adresse source.

Le rapport propose des fenetres de 7, 28 ou 90 jours, mises en cache separement 60 secondes. Il agrege sessions, rebond, pages, clics sortants, telechargements, formulaires explicitement etiquetes, origine, appareil, navigateur, systeme, taille d'ecran, profondeur de lecture, heures de visite et indicateurs de performance web. Les evenements bruts sont conserves 90 jours et la collecte ne stocke rien avant acceptation. Aucun champ saisi n'est transmis. Les evenements sont groupes par lots, limites a 240 par session et par heure ; la requete publique ne lance ni DDL ni purge. Le nettoyage se fait une fois par jour, hors requete HTTP, en lots bornes. Si une source n'est pas disponible, son panneau affiche un etat explicite.

## Serveur web

Apache : activer `mod_rewrite` si necessaire et autoriser les fichiers `.htaccess` (`AllowOverride All`). Si la racine web est ce dossier, `.env`, les fichiers temporaires et tout dossier `storage/` sont refuses par les regles locales.

Nginx : les fichiers `.htaccess` ne sont pas lus. Ajouter l'equivalent dans le bloc `server` du domaine avant de le mettre en ligne :

```nginx
location ~ /\. { deny all; }
location ^~ /storage/ { deny all; }
location ~* \.(json|lock|log|sql|tmp)$ { deny all; }
```

Forcer HTTPS au niveau du proxy ou du serveur web et ne pas transmettre un en-tete `X-Forwarded-Proto` arbitraire. La base et le fichier de cle Google devraient etre limites par pare-feu aux seuls acces necessaires.