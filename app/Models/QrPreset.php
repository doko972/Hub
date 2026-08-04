<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration enregistrée du générateur de QR code.
 *
 * La charge utile est chiffrée : elle contient des identifiants SIP et un mot
 * de passe d'administration de téléphone. Même raison d'être que le cast
 * 'encrypted' sur UserToolCredential.
 */
class QrPreset extends Model
{
    use HasFactory;

    /**
     * Nombre maximum de configurations par utilisateur : garde-fou contre une
     * table qui grossirait sans fin.
     */
    public const MAX_PER_USER = 50;

    protected $fillable = ['name', 'payload'];

    protected function casts(): array
    {
        return [
            // encrypted:array — sérialisation JSON puis chiffrement AES-256.
            'payload' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
