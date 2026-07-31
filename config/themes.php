<?php

/**
 * Catalogue des thèmes de l'interface.
 *
 * SOURCE UNIQUE DE VÉRITÉ : la validation, le menu de la navbar et la page
 * Préférences lisent tous ce fichier. Ajouter un thème = 1 bloc ici
 * + 1 bloc [data-theme="clé"] dans resources/sass/components/_themes.scss.
 *
 * Les clés DOIVENT correspondre aux sélecteurs [data-theme="…"] du SCSS.
 *
 * 'swatch' sert uniquement aux pastilles d'aperçu du sélecteur ; ce sont
 * des valeurs figées (on ne peut pas lire le CSS depuis PHP). Les garder
 * en phase avec _themes.scss quand une palette évolue.
 *   [0] fond   [1] surface   [2] accent
 *
 * 'dark' indique aux navigateurs la bonne palette pour les widgets natifs
 * (scrollbars, champs de formulaire) via color-scheme.
 */
return [

    // Thème appliqué quand l'utilisateur n'a jamais choisi.
    // 'system' = on suit prefers-color-scheme (clair ou sombre).
    'default' => 'system',

    'available' => [

        'system' => [
            'label'  => 'Automatique',
            'desc'   => 'Suit le réglage de votre appareil',
            'dark'   => null,
            'swatch' => ['#F3F4F6', '#0F172A', '#362ad7'],
        ],

        'light' => [
            'label'  => 'Clair',
            'desc'   => 'Le thème original du Hub',
            'dark'   => false,
            'swatch' => ['#F3F4F6', '#FFFFFF', '#362ad7'],
        ],

        'dark' => [
            'label'  => 'Sombre',
            'desc'   => 'Ardoise, reposant le soir',
            'dark'   => true,
            'swatch' => ['#0F172A', '#1E293B', '#7C3AED'],
        ],

        'ocean' => [
            'label'  => 'Océan',
            'desc'   => 'Bleu froid, clair et net',
            'dark'   => false,
            'swatch' => ['#EDF4FA', '#FFFFFF', '#0369A1'],
        ],

        'nord' => [
            'label'  => 'Nord',
            'desc'   => 'Bleu-gris désaturé, sombre et doux',
            'dark'   => true,
            'swatch' => ['#2E3440', '#3B4252', '#88C0D0'],
        ],

        'forest' => [
            'label'  => 'Forêt',
            'desc'   => 'Vert profond sur fond sombre',
            'dark'   => true,
            'swatch' => ['#0D1A14', '#15271E', '#34D399'],
        ],

        'sunset' => [
            'label'  => 'Crépuscule',
            'desc'   => 'Ambre et rose, sombre chaleureux',
            'dark'   => true,
            'swatch' => ['#191316', '#241A1F', '#FB7185'],
        ],

        'midnight' => [
            'label'  => 'Minuit',
            'desc'   => 'Noir pur — économise la batterie OLED',
            'dark'   => true,
            'swatch' => ['#000000', '#0A0A0A', '#A78BFA'],
        ],

        'contrast' => [
            'label'  => 'Contraste élevé',
            'desc'   => 'Accessibilité : bordures pleines, contrastes maximaux',
            'dark'   => true,
            'swatch' => ['#000000', '#1A1A1A', '#FFD400'],
        ],
    ],
];
