<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DiscussionAttachment extends Model
{
    /**
     * Disque privé : hors de public/, donc inaccessible par URL directe.
     */
    public const DISK = 'local';

    protected $fillable = ['discussion_message_id', 'path', 'original_name', 'mime_type', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        // Supprimer la ligne sans supprimer le fichier laisserait grossir le
        // disque indéfiniment.
        static::deleting(function (self $attachment) {
            Storage::disk(self::DISK)->delete($attachment->path);
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(DiscussionMessage::class, 'discussion_message_id');
    }

    /**
     * Affichable directement dans le fil ?
     *
     * Liste blanche volontairement étroite : ni SVG (document XML pouvant
     * porter du script) ni HTML n'y figurent, ils seront téléchargés.
     */
    public function isInlineImage(): bool
    {
        return in_array($this->mime_type, config('messaging.attachments.inline_mimes', []), true);
    }

    public function humanSize(): string
    {
        $octets = $this->size;

        foreach (['o', 'Ko', 'Mo'] as $unite) {
            if ($octets < 1024 || $unite === 'Mo') {
                return round($octets, $unite === 'o' ? 0 : 1) . ' ' . $unite;
            }

            $octets /= 1024;
        }

        return $this->size . ' o';
    }

    public function toPayload(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->original_name,
            'size'     => $this->humanSize(),
            'url'      => route('messages.attachment', $this),
            'is_image' => $this->isInlineImage(),
        ];
    }
}
