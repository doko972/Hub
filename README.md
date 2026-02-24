# Hub Dashboard

Tableau de bord général pour centraliser tous vos outils de travail en un seul endroit.
Chaque outil est présenté sous forme de vignette cliquable, avec gestion des accès par utilisateur.

---

## Fonctionnalités

### Pour tous les utilisateurs
- Grille de vignettes responsive (mobile → tablette → bureau)
- Tooltip de description au survol / tap mobile
- Photo de profil personnalisée (upload ou initiales générées)
- Switch thème clair / sombre (persistant via localStorage, respecte `prefers-color-scheme`)

### Pour les administrateurs
- **CRUD Outils** : titre, description, URL, image, couleur, ordre d'affichage, visibilité (public ou assigné)
- **CRUD Utilisateurs** : rôle (admin/user), statut actif/inactif, assignation des outils par utilisateur
- Protection : un administrateur ne peut pas se supprimer ni se désactiver lui-même

---

## Stack technique

| Couche       | Technologie                        |
|--------------|------------------------------------|
| Backend      | PHP 8.3 + Laravel 12               |
| Base données | MySQL 8                            |
| Assets       | Vite 6                             |
| CSS          | SASS (architecture composants)     |
| JS           | ES6 modules (sans framework)       |
| Serveur      | WAMP / Apache                      |

Aucun framework CSS (pas de Bootstrap, pas de Tailwind). Tout le style est maintenu manuellement via les composants SASS.

---

## Prérequis

- PHP >= 8.2
- Composer
- Node.js >= 18 + npm
- MySQL 8
- Serveur web (Apache / WAMP recommandé sur Windows)

---

## Installation

### 1. Cloner le projet

```bash
git clone <url-du-repo> dashboard-general
cd dashboard-general
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Installer les dépendances JS

```bash
npm install
```

### 4. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :

```dotenv
APP_URL=http://localhost/dashboard-general/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dashboard_general
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### 5. Créer la base de données

```sql
CREATE DATABASE dashboard_general CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Exécuter les migrations et le seeder

```bash
php artisan migrate --seed
```

### 7. Créer le lien symbolique pour le stockage

```bash
php artisan storage:link
```

### 8. Compiler les assets

```bash
# Production
npm run build

# Développement (hot reload)
npm run dev
```

---

## Comptes de démonstration

| Rôle          | Email             | Mot de passe |
|---------------|-------------------|--------------|
| Administrateur | admin@hub.local  | password     |
| Utilisateur    | user@hub.local   | password     |

---

## Structure du projet

```
dashboard-general/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php          ← connexion / déconnexion
│   │   │   ├── DashboardController.php     ← grille des vignettes
│   │   │   ├── ProfileController.php       ← profil + avatar
│   │   │   └── Admin/
│   │   │       ├── ToolController.php      ← CRUD outils
│   │   │       └── UserController.php      ← CRUD utilisateurs
│   │   └── Middleware/
│   │       └── AdminMiddleware.php         ← protection routes /admin
│   └── Models/
│       ├── User.php                        ← rôles, avatar, initiales, outils visibles
│       └── Tool.php                        ← couleurs disponibles, URL image
│
├── database/
│   ├── migrations/
│   │   ├── ..._create_users_table.php      ← + role, is_active, avatar_path
│   │   ├── ..._create_tools_table.php
│   │   └── ..._create_tool_user_table.php  ← pivot many-to-many
│   └── seeders/
│       └── DatabaseSeeder.php              ← admin + user + 4 outils de démo
│
├── resources/
│   ├── sass/
│   │   ├── app.scss                        ← point d'entrée (ordre d'import important)
│   │   └── components/
│   │       ├── _variables.scss             ← tokens de design (couleurs, espacements...)
│   │       ├── _reset.scss                 ← normalisation + utilitaires de base
│   │       ├── _layout.scss                ← app-wrapper, main-content, page-content
│   │       ├── _navbar.scss                ← barre de navigation + theme toggle
│   │       ├── _sidebar.scss               ← sidebar fixe + mini-profil
│   │       ├── _burger.scss                ← animation 3 lignes → croix
│   │       ├── _buttons.scss               ← btn--primary / secondary / ghost / danger
│   │       ├── _forms.scss                 ← inputs, upload, toggles, labels
│   │       ├── _tiles.scss                 ← grille vignettes + tooltip hover
│   │       ├── _admin.scss                 ← tables, badges, stat-cards
│   │       ├── _auth.scss                  ← page de connexion centrée
│   │       ├── _profile.scss               ← layout deux colonnes page profil
│   │       └── _dark.scss                  ← overrides thème sombre (toujours en dernier)
│   │
│   ├── js/
│   │   ├── app.js                          ← point d'entrée (imports + DOMContentLoaded)
│   │   └── components/
│   │       ├── theme.js                    ← dark/light switch + localStorage + anti-flash
│   │       ├── burger.js                   ← ouverture sidebar mobile + overlay
│   │       ├── dropdown.js                 ← menu utilisateur (click en dehors pour fermer)
│   │       ├── tooltip.js                  ← tap mobile pour afficher les tooltips
│   │       ├── imagePreview.js             ← prévisualisation avant upload + auto-submit avatar
│   │       └── confirmDelete.js            ← confirmation avant suppression
│   │
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php               ← layout principal (sidebar + navbar + flash)
│       ├── auth/
│       │   └── login.blade.php
│       ├── dashboard/
│       │   └── index.blade.php             ← grille des vignettes
│       ├── profile/
│       │   └── edit.blade.php              ← avatar + infos personnelles
│       └── admin/
│           ├── tools/                      ← index, create, edit, _form
│           └── users/                      ← index, create, edit, _form
│
├── routes/
│   └── web.php                             ← routes auth, dashboard, profil, admin
│
├── vite.config.js
├── package.json
└── composer.json
```

---

## Thème sombre

Le thème sombre est géré entièrement par CSS custom properties et l'attribut `data-theme="dark"` sur la balise `<html>`.

- Priorité : préférence sauvegardée (`localStorage`) > préférence système (`prefers-color-scheme`) > clair par défaut
- Anti-flash : un script inline dans `<head>` applique le thème avant le rendu CSS
- Le bouton (icône lune / soleil) se trouve dans la navbar

---

## Gestion des accès aux outils

Un outil peut être :
- **Public** (`is_public = true`) : visible par tous les utilisateurs connectés
- **Assigné** : visible uniquement par les utilisateurs explicitement associés via la table pivot `tool_user`

Les administrateurs voient tous les outils, qu'ils soient publics, assignés ou inactifs.

---

## Développement

```bash
# Lancer le serveur de dev Vite (hot reload CSS + JS)
npm run dev

# Recompiler pour la production
npm run build

# Réinitialiser la base de données
php artisan migrate:fresh --seed
```

---

## Notes d'architecture

- **Pas de framework CSS** : tout est écrit en SASS avec une nomenclature BEM. Facile à lire, modifier et supprimer.
- **SASS `@import`** utilisé à la place de `@use` pour permettre le partage des variables entre tous les fichiers composants.
- **ES6 modules** : chaque fonctionnalité JS est dans son propre fichier, exportée et importée dans `app.js`.
- **Aucune dépendance JS externe** : pas de jQuery, pas d'Alpine, pas de Vue.
