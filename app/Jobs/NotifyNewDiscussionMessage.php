<?php

namespace App\Jobs;

use App\Models\DiscussionMessage;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Prévient par notification push les participants d'un fil, sauf l'auteur.
 *
 * Le service worker se charge de ne rien afficher si le destinataire a déjà la
 * discussion ouverte et au premier plan : le serveur n'a aucun moyen fiable de
 * le savoir.
 */
class NotifyNewDiscussionMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $messageId)
    {
    }

    public function handle(PushService $push): void
    {
        if (!PushService::isConfigured()) {
            return;
        }

        $message = DiscussionMessage::with(['author', 'discussion.participants'])->find($this->messageId);

        // Le message a pu être supprimé entre l'envoi et le traitement.
        if (!$message || !$message->discussion) {
            return;
        }

        $discussion = $message->discussion;
        $author     = $message->author?->name ?? 'Quelqu\'un';
        $url        = "/messages/{$discussion->id}";
        $extrait    = Str::limit($message->body, 120);

        foreach ($discussion->participants as $participant) {
            if ($participant->id === $message->user_id) {
                continue;
            }

            // En groupe, le titre nomme le fil et le corps précise qui parle ;
            // à deux, le titre suffit à identifier l'interlocuteur.
            [$titre, $corps] = $discussion->is_group
                ? [$discussion->titleFor($participant->id), "{$author} : {$extrait}"]
                : [$author, $extrait];

            $push->sendToUser($participant->id, $titre, $corps, $url, [
                // Regroupe les notifications d'un même fil : un échange animé
                // ne produit pas une pile de bannières.
                'tag'      => "discussion-{$discussion->id}",
                'renotify' => true,
            ]);
        }
    }
}
