<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionMessage extends Model
{
    use HasFactory;

    protected $fillable = ['discussion_id', 'user_id', 'body'];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Représentation destinée au client (sondage et rendu initial).
     */
    public function toPayload(int $currentUserId): array
    {
        return [
            'id'        => $this->id,
            'body'      => $this->body,
            'author'    => $this->author?->name ?? 'Utilisateur supprimé',
            'initials'  => $this->author?->initials() ?? '?',
            'avatar'    => $this->author?->avatarUrl(),
            'is_mine'   => $this->user_id === $currentUserId,
            'sent_at'   => $this->created_at->locale('fr')->isoFormat('D MMM à HH:mm'),
        ];
    }
}
