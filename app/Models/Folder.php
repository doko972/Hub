<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'position',
    ];

    /**
     * Le propriétaire du dossier
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les conversations dans ce dossier
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}