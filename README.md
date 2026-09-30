<div align="center">

# 🥋 Warriors Training Club

### Application web de gestion d'un club sportif

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](#)
[![MySQL](https://img.shields.io/badge/MySQL-DB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](#)
[![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)](#)
[![Offline](https://img.shields.io/badge/Mode%20hors%20ligne-Enabled-2ea44f?style=for-the-badge)](#)

</div>

---

## Présentation

Warriors Training Club est une application PHP/MySQL pensée pour la gestion quotidienne d'un club de sport. Elle couvre l'adhésion des membres, la planification des séances, les classements, les signalements, l'administration et les automatisations de communication.

Le projet est conçu comme une plateforme de club autonome, avec des pages publiques, un espace membre et un back-office d'administration.

---

## Fonctionnalités principales

- Inscription et connexion sécurisées
- Vérification d'email et réinitialisation de mot de passe
- Acceptation du règlement intérieur avant accès complet
- Gestion du profil utilisateur : photo, mot de passe, préférences email, suppression de compte
- Planning des séances avec inscriptions et gestion des templates
- Classement avec catégories, sous-catégories, participants et photos
- Signalement utilisateur avec limite anti-abus
- Tableau de bord administrateur
- Gestion des utilisateurs, bannissement et maintenance globale
- Envoi d'emails à tous les adhérents
- Audit SQL en direct pour les actions administrateur
- Dashboard GitHub avec les derniers commits
- PWA + support hors ligne via service worker
- Intégration IndexNow / webhook GitHub pour le SEO technique

---

## Structure du projet

```text
Warriors-Training-Club-App/
├── index.php                     # Page d'accueil
├── connexion.php                 # Connexion membre
├── inscription.php               # Inscription
├── mot-de-passe-oublie.php       # Demande de reset
├── reinitialiser-mot-de-passe.php
├── verify.php                    # Vérification du compte
├── reglement-interieur.php       # Règlement intérieur
├── reglement-accept.php          # Validation du règlement
├── modifier-profil.php           # Profil utilisateur
├── seances.php                   # Gestion des séances
├── classement.php                # Page de classement
├── signalements.php              # Formulaire de signalement
├── reports.php                   # Vue admin des signalements
├── utilisateurs.php              # Administration des comptes
├── actions-sql.php               # Audits SQL en direct
├── commits-dashboard.php         # Dashboard GitHub
├── envoyer-mail.php              # Envoi d'emails
├── liens-index.php               # Liens de la page d'accueil
├── settings.php                  # Paramètres applicatifs
├── github-webhook.php            # Webhook GitHub
├── indexnow.php                  # Soumission IndexNow
├── offline.html                 # Page de secours hors ligne
├── manifest.json                 # Manifest PWA
├── sw.js                        # Service worker
├── db.sql                       # Schéma SQL de base
├── composer.json                # Dépendances PHP
├── .env                         # Variables d'environnement locales
├── data/                        # Données JSON et fichiers de flux
├── img/                         # Images du site et photos de profil
├── css/                         # Styles globaux
├── js/                          # Scripts front
├── api/                         # API interne pour classement
├── includes/                    # Logique applicative
│   ├── account/                 # Inscription, connexion, profil
│   ├── general/                 # Session, DB, mailer, sécurité, admin
│   └── seances/                 # Gestion des séances
├── vendor/                      # Dépendances Composer
├── README.md                    # Documentation du projet
└── .htaccess / nginx rules      # À configurer côté serveur
```

---

## Prérequis

- PHP 8.1 ou supérieur
- MySQL/MariaDB
- Composer
- Un serveur web compatible (Apache ou Nginx)
- Extension PHP `curl` et `mbstring`

---

## Démarrage rapide

1. Installer les dépendances :

```bash
composer install
```

2. Créer ou compléter le fichier `.env` à la racine avec les variables de base :

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=warriors_training_club
DB_USERNAME=votre_utilisateur
DB_PASSWORD=votre_mot_de_passe

APP_BASE_URL=http://localhost:8000

MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=club@example.com
MAIL_PASSWORD=secret
MAIL_FROM=club@example.com
MAIL_FROM_NAME="Warriors Training Club"
MAIL_ENCRYPTION=tls

INDEXNOW_KEY=votre_cle_indexnow
INDEXNOW_HTTP_TOKEN=votre_token_http
INDEXNOW_SITE_URL=http://localhost:8000
```

3. Importer le schéma SQL :

```bash
mysql -u votre_utilisateur -p votre_base < db.sql
```

4. Lancer le projet localement :

```bash
php -S localhost:8000
```

Puis ouvrir :

```text
http://localhost:8000
```

---

## Configuration métier

### Base de données

Le projet attend une base MySQL avec les tables créées par `db.sql`. Les éléments majeurs sont :

- `account_wtc` : comptes utilisateur
- `seances` : planning des séances
- `inscriptions_seances` : inscriptions aux séances
- `signalements_wtc` : signalements utilisateurs
- `index_links` : liens d'accueil
- `ilyc_scores` : scores du classement / défi
- `ranking_*` : catégories et résultats de classement

### Emails

Les messages de vérification, de réinitialisation, de signalement et de newsletter passent par `includes/general/mailer.php` et utilisent les variables suivantes :

- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM`
- `MAIL_FROM_NAME`
- `MAIL_ENCRYPTION`

### IndexNow / SEO technique

Le script `indexnow.php` permet d'envoyer les URLs du site à IndexNow. Le webhook GitHub `github-webhook.php` peut aussi déclencher automatiquement cette soumission après un push.

---

## Pages et modules clés

### Côté public

- `index.php` : accueil, informations du club, liens utiles, contenus publics
- `reglement-interieur.php` : règlement intérieur et conditions d'usage
- `seances.php` : calendrier et inscriptions aux séances
- `classement.php` : écran principal du classement
- `offline.html` : fallback hors ligne pour la PWA

### Côté membre

- `connexion.php` / `inscription.php`
- `mot-de-passe-oublie.php` / `reinitialiser-mot-de-passe.php`
- `verify.php`
- `modifier-profil.php`
- `ban.php`

### Côté administration

- `reports.php` : consultation des signalements
- `utilisateurs.php` : gestion des comptes et droits
- `actions-sql.php` : journalisation des requêtes SQL
- `envoyer-mail.php` : email groupé
- `liens-index.php` : modification des liens d'accueil
- `commits-dashboard.php` : suivi des commits GitHub
- `settings.php` : paramètres globaux de l'application

---

## Sécurité et bonnes pratiques

- Les sessions sont configurées côté PHP via `includes/general/session-config.php`
- Les vérifications de session et de droits sont centralisées dans `includes/general/verifications.php`
- L'accès admin est contrôlé côté serveur avant chaque action critique
- Le système de mail et les données persistantes sont configurés via variables d'environnement
- Le site propose un mode maintenance et des mécanismes de bannissement utilisateur

---

## Déploiement

Pour un déploiement sur un hébergement classique :

1. Copier le projet sur le serveur
2. Installer les dépendances avec Composer
3. Configurer le `.env` avec les valeurs de production
4. Importer `db.sql`
5. Vérifier les droits d'écriture sur les dossiers `data/`, `img/`, et `img/pdps/`
6. Configurer le certificat HTTPS et le domaine public
7. Mettre en place un cron ou un webhook si vous utilisez `github-webhook.php` / `indexnow.php`

---

<div align="center">

Fait avec 🥋 pour la communauté du club.

</div>
