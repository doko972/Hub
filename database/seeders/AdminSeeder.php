<?php

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Administrateur par défaut ----
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.local'],
            [
                'name'      => 'Administrateur',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
