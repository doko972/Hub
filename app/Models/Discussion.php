<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Un fil de discussion, à deux ou en groupe.
 *
 * Les deux cas partagent la même structure : une conversation directe est un
 * groupe de deux personnes sans nom. Cela évite de dédoubler les requêtes,
 * l'affichage et les contrôles d'accès.
 */
class Discussion extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_group', 'direct_key', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'is_group'        => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(DiscussionMessage::class)->latestOfMany();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Conversations directes ----

    /**
     * Empreinte stable d'une paire, indépendante de l'ordre des identifiants.
     */
    public static function directKey(int $a, int $b): string
    {
        return min($a, $b) . '-' . max($a, $b);
    }

    /**
     * Récupère la conversation entre deux personnes, ou la crée.
     *
     * L'index unique sur direct_key garantit l'unicité même en cas d'ouverture
     * simultanée des deux côtés : la seconde insertion échoue et l'on relit.
     */
    public static function findOrCreateDirect(int $userId, int $otherId): self
    {
        if ($userId === $otherId) {
            throw new \InvalidArgumentException('Une conversation directe demande deux personnes distinctes.');
        }

        $key = self::directKey($userId, $otherId);

        if ($existing = self::where('direct_key', $key)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($key, $userId, $otherId) {
            $discussion = self::create([
                'is_group'        => false,
                'direct_key'      => $key,
                'created_by'      => $userId,
                'last_message_at' => now(),
            ]);

            $discussion->participants()->attach([$userId, $otherId]);

            return $discussion;
        });
    }

    // ---- Affichage ----

    /**
     * Titre du fil du point de vue d'un participant : le nom du groupe, ou
     * celui de l'interlocuteur.
     */
    public function titleFor(int $userId): string
    {
        if ($this->is_group) {
            return $this->name ?: 'Groupe sans nom';
        }

        $other = $this->participants->firstWhere('id', '!=', $userId);

        return $other?->name ?? 'Utilisateur supprimé';
    }

    /**
     * Interlocuteur d'une conversation directe (null pour un groupe).
     */
    public function counterpartFor(int $userId): ?User
    {
        return $this->is_group ? null : $this->participants->firstWhere('id', '!=', $userId);
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants->contains('id', $userId);
    }
}
