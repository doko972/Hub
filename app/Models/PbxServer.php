<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un centrex FreePBX hébergé sur une VM.
 *
 * La fiche est un accès, pas une simple URL : elle porte les identifiants
 * d'administration, chiffrés au repos et servis uniquement à la demande
 * (voir Tools\PbxServerController::secret).
 */
class PbxServer extends Model
{
    protected $fillable = [
        'name',
        'client',
        'protocol',
        'host',
        'ovh_service_name',
        'ovh_project',
        'port',
        'path',
        'login',
        'password',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'password'  => 'encrypted', // AES-256 via APP_KEY
            'is_active' => 'boolean',
            'port'      => 'integer',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Hôte tel qu'on l'écrit dans un navigateur : « 10.0.0.12 » ou
     * « pbx.exemple.fr:8443 ».
     */
    public function hostLabel(): string
    {
        return $this->port ? $this->host . ':' . $this->port : $this->host;
    }

    /**
     * URL d'administration, reconstruite à partir des composants validés.
     * Rien de ce qui est saisi n'arrive tel quel dans un href.
     */
    public function url(): string
    {
        $port = $this->port ? ':' . $this->port : '';
        $path = $this->path ? '/' . ltrim($this->path, '/') : '';

        return $this->protocol . '://' . $this->host . $port . $path;
    }

    /**
     * Protocoles autorisés — un select, pas un champ libre.
     */
    public static function availableProtocols(): array
    {
        // HTTP en tête : c'est le protocole par défaut des interfaces
        // d'administration des centrex, rarement exposées en TLS.
        return ['http' => 'HTTP', 'https' => 'HTTPS'];
    }
}
