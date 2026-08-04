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
        'theme',
        'google_id',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'last_seen_at',
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
            'last_seen_at'            => 'datetime',
            // Chiffrés au repos (AES-256 via APP_KEY) : un dump de la base ne
            // doit pas donner accès aux agendas Google des utilisateurs.
            'google_access_token'     => 'encrypted',
            'google_refresh_token'    => 'encrypted',
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

    // ---- Présence ----

    /**
     * Considéré connecté si une activité a été enregistrée récemment
     * (voir config/presence.php).
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes((int) config('presence.online_within_minutes')));
    }

    // ---- Thème d'interface ----

    /**
     * Thème effectif, en se rabattant sur le défaut si la valeur stockée
     * n'existe plus (thème retiré de config/themes.php, colonne vide).
     *
     * Volontairement PAS nommée theme() : Eloquent traiterait la méthode
     * comme une relation si la colonne du même nom venait à manquer.
     */
    public function effectiveTheme(): string
    {
        $available = array_keys(config('themes.available', []));

        return in_array($this->theme, $available, true)
            ? $this->theme
            : config('themes.default', 'system');
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

    // ---- Messagerie interne ----
    public function discussions()
    {
        return $this->belongsToMany(Discussion::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    // ---- Générateur de QR code ----
    public function qrPresets()
    {
        return $this->hasMany(QrPreset::class)->orderBy('name');
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
