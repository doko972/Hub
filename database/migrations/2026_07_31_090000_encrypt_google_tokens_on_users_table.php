<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Chiffre les tokens Google déjà stockés en clair.
 *
 * Le cast 'encrypted' ajouté sur le modèle User ne s'applique qu'aux écritures :
 * les lignes existantes doivent être converties une fois, sinon leur lecture
 * lèvera une DecryptException.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->select('id', 'google_access_token', 'google_refresh_token')
            ->whereNotNull('google_access_token')
            ->orWhereNotNull('google_refresh_token')
            ->orderBy('id')
            ->chunk(200, function ($users) {
                foreach ($users as $user) {
                    $updates = [];

                    foreach (['google_access_token', 'google_refresh_token'] as $column) {
                        $value = $user->{$column};

                        if ($value === null || $value === '' || $this->isEncrypted($value)) {
                            continue;
                        }

                        $updates[$column] = Crypt::encryptString($value);
                    }

                    if ($updates) {
                        DB::table('users')->where('id', $user->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->select('id', 'google_access_token', 'google_refresh_token')
            ->whereNotNull('google_access_token')
            ->orWhereNotNull('google_refresh_token')
            ->orderBy('id')
            ->chunk(200, function ($users) {
                foreach ($users as $user) {
                    $updates = [];

                    foreach (['google_access_token', 'google_refresh_token'] as $column) {
                        $value = $user->{$column};

                        if ($value === null || $value === '' || !$this->isEncrypted($value)) {
                            continue;
                        }

                        $updates[$column] = Crypt::decryptString($value);
                    }

                    if ($updates) {
                        DB::table('users')->where('id', $user->id)->update($updates);
                    }
                }
            });
    }

    /**
     * Une valeur déjà chiffrée se déchiffre sans erreur — rend la migration
     * rejouable sans double chiffrement.
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
