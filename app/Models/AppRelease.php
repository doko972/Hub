<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = [
        'version',
        'platform',
        'changelog',
        'file_path',
        'file_name',
        'file_size',
        'signature',
        'is_active',
        'is_mandatory',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    /**
     * Récupère la dernière version active pour une plateforme
     */
    public static function getLatestForPlatform(string $platform): ?self
    {
        return self::where('platform', $platform)
            ->where('is_active', true)
            ->orderByRaw("CAST(SUBSTRING_INDEX(version, '.', 1) AS UNSIGNED) DESC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(version, '.', 2), '.', -1) AS UNSIGNED) DESC")
            ->orderByRaw("CAST(SUBSTRING_INDEX(version, '.', -1) AS UNSIGNED) DESC")
            ->first();
    }

    /**
     * Compare deux versions (retourne true si $version est inférieure à $this->version)
     */
    public function isNewerThan(string $version): bool
    {
        return version_compare($this->version, $version, '>');
    }
}