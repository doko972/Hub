# HUB DASHBOARD
## Gestionnaire de projet


Structure des fichiers clés
├── resources/
│   ├── sass/
│   │   ├── app.scss                    ← point d'entrée
│   │   └── components/
│   │       ├── _variables.scss         ← toutes les valeurs
│   │       ├── _reset.scss
│   │       ├── _layout.scss
│   │       ├── _navbar.scss
│   │       ├── _sidebar.scss
│   │       ├── _burger.scss            ← animation 3 barres → croix
│   │       ├── _buttons.scss
│   │       ├── _forms.scss             ← upload image, toggles
│   │       ├── _tiles.scss             ← vignettes + tooltip hover
│   │       ├── _admin.scss             ← tables, badges
│   │       └── _auth.scss
│   └── js/
│       ├── app.js                      ← point d'entrée
│       └── components/
│           ├── burger.js               ← sidebar responsive
│           ├── dropdown.js             ← menu utilisateur
│           ├── tooltip.js              ← tap mobile
│           ├── imagePreview.js         ← prévisualisation upload
│           └── confirmDelete.js        ← confirm avant suppression
├── app/
│   ├── Models/          User, Tool
│   ├── Controllers/     Auth, Dashboard, Admin/Tool, Admin/User
│   └── Middleware/      AdminMiddleware
└── database/
    ├── migrations/      users + tools + tool_user
    └── seeders/         admin + user demo + 4 outils


## Fonctionnalités actives :

- Grille responsive auto-fill (2 colonnes mobile → autant que l'écran permet)
- Tooltip description au survol (CSS pur) + tap sur mobile (JS)
- Burger menu avec animation slide-in sidebar
- CRUD complet outils : titre, description, URL, image upload, couleur, ordre, public/assigné
- CRUD complet users : rôle, statut actif, assignation des outils
- Protection : un admin ne peut pas se supprimer/désactiver lui-même