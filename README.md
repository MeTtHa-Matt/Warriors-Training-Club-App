<div align="center">

# 🥋 Warriors Training Club

### Application web de gestion de club sportif — séances, membres & administration

[![PHP](https://img.shields.io/badge/PHP-Back--end-777BB4?style=for-the-badge&logo=php&logoColor=white)](#)
[![PWA](https://img.shields.io/badge/PWA-Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)](#)
[![Offline](https://img.shields.io/badge/Mode-Hors%20ligne-2ea44f?style=for-the-badge&logo=cachet&logoColor=white)](#)
[![License](https://img.shields.io/badge/Licence-À%20définir-lightgrey?style=for-the-badge)](#)

</div>

---

## 🥊 Description

**Warriors Training Club** est une application web complète destinée à la gestion d'un club sportif : inscription des membres, planning des séances, signalements, et back-office d'administration. Le site inclut une couche **PWA** légère permettant un fonctionnement partiel hors ligne.

---

## 📁 Structure du projet

```
warriors-training-club/
├── index.php                      # Page d'accueil du site
├── reglement-interieur.php         # Règlement intérieur du club
├── offline.html                    # Page de secours hors ligne
│
├── inscription.php                 # Inscription d'un nouveau membre
├── connexion.php                   # Connexion des membres
├── mot-de-passe-oublie.php          # Demande de réinitialisation
├── reinitialiser-mot-de-passe.php   # Choix d'un nouveau mot de passe
├── verify.php                      # Vérification d'email par token
├── reglement-accept.php             # Acceptation obligatoire du règlement
├── modifier-profil.php              # Modification du profil utilisateur
├── ban.php                         # Page dédiée aux comptes bannis
│
├── seances.php                     # Calendrier et gestion des séances
├── signalements.php                 # Formulaire de signalement
│
├── administration.php               # Tableau de bord admin
├── utilisateurs.php                 # Gestion des comptes utilisateurs
├── envoyer-mail.php                  # Envoi d'emails à tous les membres
├── liens-index.php                  # Édition des liens de la page d'accueil
│
├── includes/
│   ├── seances/                     # Logique métier des séances
│   ├── account/                     # Traitements liés au compte (inscription, connexion...)
│   └── general/                     # Configuration, sessions, DB, mailer
│
├── js/
│   ├── seances.js                   # Comportement client du calendrier
│   ├── accept-email-toggle.js        # Toggle d'acceptation des emails
│   └── envoyer-mail.js               # Éditeur riche pour l'envoi de mails
│
├── css/style.css                    # Styles du site
├── manifest.json                    # Déclaration PWA
├── sw.js                            # Service worker (cache & offline)
└── img/ , img/pdps/                  # Ressources graphiques & photos de profil
```

---

## 🌐 Pages publiques

| Page | Rôle |
|---|---|
| `index.php` | Accueil : présentation du club, horaires de saison, liens vers l'inscription et la boutique, documents de santé, carte de localisation, liens externes (HelloAsso, Market Factory) |
| `reglement-interieur.php` | Règlement intérieur : obligations des adhérents, règles de paiement, responsabilité des mineurs, hygiène, usage du site |
| `offline.html` | Page affichée automatiquement lorsque le site est hors ligne |

---

## 🔐 Authentification & compte utilisateur

- 📝 **Inscription** (`inscription.php`) : prénom, nom, email, mot de passe, photo de profil facultative
- 🔑 **Connexion** (`connexion.php`) : email + mot de passe
- ♻️ **Mot de passe oublié** (`mot-de-passe-oublie.php` → `reinitialiser-mot-de-passe.php`) : envoi d'un lien de réinitialisation par email
- ✅ **Vérification d'email** (`verify.php`) : validation via token URL
- 📜 **Acceptation du règlement** (`reglement-accept.php`) : obligatoire après connexion, redirection vers l'accueil une fois validée
- 👤 **Modification du profil** (`modifier-profil.php`) : infos personnelles, photo, mot de passe, préférences email, **suppression définitive du compte**
- 🚫 **Comptes bannis** (`ban.php`) : page dédiée avec possibilité de se déconnecter

---

## 📅 Gestion des séances

- Calendrier interactif des séances (`seances.php` + `js/seances.js`)
- Liste des prochaines séances
- Modales : détail, inscription (soi-même ou un tiers), liste des inscrits, modification
- Ajout de séance & application de **templates** pour les utilisateurs autorisés

**Logique métier** répartie dans `includes/seances/` :
`ajouter.php` · `modifier_seance.php` · `supprimer_seance.php` · `detail.php` · `inscrire.php` · `inscrits.php` · `mes_inscriptions.php` · `templates.php` · `apply_template.php` · `update_template_seances.php` · `list_month.php` · `template_helpers.php`

---

## 🚨 Signalements

- Formulaire de signalement des problèmes rencontrés (`signalements.php`)
- ⏱️ Limité à **3 signalements par semaine** par utilisateur
- Enregistrement du message + **notification email**

---

## 🛠️ Administration

| Page | Fonction |
|---|---|
| `administration.php` | Tableau de bord : statistiques comptes, sessions, inscriptions, signalements, opt-outs email, mode maintenance |
| `utilisateurs.php` | Recherche/filtre des comptes · promotion admin · gestion des séances · bannir/débannir · mode maintenance global |
| `envoyer-mail.php` | Envoi d'un email enrichi à tous les utilisateurs, avec pièces jointes et signature |
| `liens-index.php` | Mise à jour des liens et boutons affichés sur la page d'accueil |

---

## ⚙️ Inclusions & back-office

| Fichier | Rôle |
|---|---|
| `includes/general/session-config.php` | Configuration sécurisée des sessions, en-têtes de cache, protection XSS |
| `includes/general/verifications.php` | Vérification des sessions, redirection (bannissement / maintenance / règlement) |
| `includes/general/db.php` | Connexion à la base de données |
| `includes/general/persistent-auth.php` | Authentification persistante ("rester connecté") |
| `includes/general/mailer.php` | Envoi des emails (signalements, réinitialisation, notifications) |
| `includes/account/*` | Logique de compte : inscription, connexion, déconnexion, mise à jour du profil, préférences email |

---

## 📲 PWA & fichiers statiques

- `manifest.json` : déclaration PWA de l'application
- `sw.js` : service worker pour la mise en cache et le mode hors ligne
- `css/style.css` : styles globaux du site
- `img/`, `img/pdps/` : ressources graphiques et photos de profil

---

## ✨ Fonctionnalités clés

- 🔐 Inscription et connexion sécurisées, avec vérification par email
- 📜 Acceptation obligatoire du règlement intérieur
- 👤 Gestion complète du compte : modification, photo, mot de passe, suppression
- 🗓️ Gestion des séances avec calendrier, templates et inscriptions
- 🚨 Signalement de problèmes avec limitation anti-abus
- 🛠️ Tableau de bord admin et gestion fine des utilisateurs
- ✉️ Envoi d'emails groupés à tous les membres
- 🔗 Édition des liens de la page d'accueil
- 🚧 Mode maintenance global et bannissement de comptes
- 📴 PWA légère avec cache et page de secours hors ligne

---

<div align="center">

Fait avec 🥋 pour la communauté du club.

</div>