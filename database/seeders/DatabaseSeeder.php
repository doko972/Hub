<?php

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Administrateur par défaut ----
        $admin = User::firstOrCreate(
            ['email' => 'admin@hub.local'],
            [
                'name'      => 'Administrateur',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // ---- Utilisateur de démonstration ----
        $user = User::firstOrCreate(
            ['email' => 'user@hub.local'],
            [
                'name'      => 'Utilisateur Demo',
                'password'  => Hash::make('password'),
                'role'      => 'user',
                'is_active' => true,
            ]
        );

        // ---- Familles d'outils ----
        $familyDev = ToolFamily::firstOrCreate(
            ['name' => 'Développement'],
            [
                'description' => 'Outils de développement et de documentation.',
                'color'       => 'violet',
                'sort_order'  => 1,
                'is_active'   => true,
            ]
        );

        $familyInfra = ToolFamily::firstOrCreate(
            ['name' => 'Infra & Monitoring'],
            [
                'description' => 'Supervision des serveurs et statistiques.',
                'color'       => 'green',
                'sort_order'  => 2,
                'is_active'   => true,
            ]
        );

        // ---- Outils de démonstration ----
        $tools = [
            [
                'title'          => 'Documentation',
                'description'    => 'Accédez à la documentation technique de vos projets.',
                'url'            => 'https://laravel.com/docs',
                'color'          => 'violet',
                'tool_family_id' => $familyDev->id,
                'is_active'      => true,
                'is_public'      => true,
                'sort_order'     => 1,
            ],
            [
                'title'          => 'Gestion de projet',
                'description'    => 'Suivez l\'avancement de vos projets et tâches.',
                'url'            => 'https://trello.com',
                'color'          => 'blue',
                'tool_family_id' => $familyDev->id,
                'is_active'      => true,
                'is_public'      => true,
                'sort_order'     => 2,
            ],
            [
                'title'          => 'Monitoring',
                'description'    => 'Tableau de bord de supervision des serveurs.',
                'url'            => 'https://exemple.com/monitoring',
                'color'          => 'green',
                'tool_family_id' => $familyInfra->id,
                'is_active'      => true,
                'is_public'      => false,
                'sort_order'     => 1,
            ],
            [
                'title'          => 'Analytics',
                'description'    => 'Statistiques de fréquentation de vos applications.',
                'url'            => 'https://exemple.com/analytics',
                'color'          => 'orange',
                'tool_family_id' => $familyInfra->id,
                'is_active'      => true,
                'is_public'      => false,
                'sort_order'     => 2,
            ],
        ];

        $createdTools = [];
        foreach ($tools as $toolData) {
            $createdTools[] = Tool::firstOrCreate(
                ['title' => $toolData['title']],
                $toolData
            );
        }

        // Assigner les outils privés à l'utilisateur demo
        $user->tools()->syncWithoutDetaching(
            collect($createdTools)->where('is_public', false)->pluck('id')->toArray()
        );
    }
}
