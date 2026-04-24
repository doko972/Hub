<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Conversation;
use App\Models\Folder;
use App\Models\SystemPrompt;
use App\Models\UserMemory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'avatar_path',
        'google_id',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_access_token',
        'google_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'is_active'               => 'boolean',
            'is_admin'                => 'boolean',
            'google_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Synchronise automatiquement is_admin avec role à chaque sauvegarde.
     * Chatbot-api lit is_admin directement — on le garde en phase.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $user) {
            $user->is_admin = ($user->role === 'admin');
        });
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

    // ---- Relations chatbot ----
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function systemPrompts()
    {
        return $this->hasMany(SystemPrompt::class);
    }

    public function memories()
    {
        return $this->hasMany(UserMemory::class);
    }

    // ---- Relations dashboard ----
    public function tools()
    {
        return $this->belongsToMany(Tool::class)->withTimestamps();
    }

    public function selectedTools()
    {
        return $this->belongsToMany(Tool::class, 'user_tool_selection');
    }

    public function visibleTools()
    {
        if ($this->isAdmin()) {
            return Tool::where('is_active', true)->orderBy('sort_order')->orderBy('title')->get();
        }

        if ($this->tools()->exists()) {
            return Tool::where('is_active', true)
                ->whereHas('users', fn($q) => $q->where('users.id', $this->id))
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();
        }

        return Tool::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }
}
