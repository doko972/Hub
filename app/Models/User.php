<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ---- Helpers de rôle ----
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    // ---- Avatar ----
    public function avatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }
        return null;
    }

    // ---- Initiales pour l'avatar (fallback si pas de photo) ----
    public function initials(): string
    {
        $words = explode(' ', trim($this->name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    // ---- Relations ----
    public function tools()
    {
        return $this->belongsToMany(Tool::class)->withTimestamps();
    }

    // Outils visibles par cet utilisateur (publics + ceux qui lui sont assignés)
    public function visibleTools()
    {
        if ($this->isAdmin()) {
            return Tool::where('is_active', true)->orderBy('sort_order')->orderBy('title')->get();
        }

        return Tool::where('is_active', true)
            ->where(function ($q) {
                $q->where('is_public', true)
                  ->orWhereHas('users', fn($u) => $u->where('users.id', $this->id));
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }
}
