<?php

namespace App\Policies;

use App\Models\Discussion;
use App\Models\User;

/**
 * Règle unique : on ne voit et on n'écrit que dans les fils dont on fait
 * partie. Découverte automatique par Laravel (App\Models\Discussion →
 * App\Policies\DiscussionPolicy), aucun enregistrement nécessaire.
 */
class DiscussionPolicy
{
    public function view(User $user, Discussion $discussion): bool
    {
        return $this->isParticipant($user, $discussion);
    }

    public function send(User $user, Discussion $discussion): bool
    {
        return $this->isParticipant($user, $discussion);
    }

    /**
     * Inviter quelqu'un ou renommer : réservé au groupe, et à qui l'a créé.
     */
    public function manage(User $user, Discussion $discussion): bool
    {
        return $discussion->is_group
            && $discussion->created_by === $user->id
            && $this->isParticipant($user, $discussion);
    }

    /**
     * On quitte un groupe, pas une conversation à deux : la quitter
     * reviendrait à la supprimer chez l'autre.
     */
    public function leave(User $user, Discussion $discussion): bool
    {
        return $discussion->is_group && $this->isParticipant($user, $discussion);
    }

    private function isParticipant(User $user, Discussion $discussion): bool
    {
        // relationLoaded évite une requête par vérification quand la relation
        // a déjà été chargée par le contrôleur.
        if ($discussion->relationLoaded('participants')) {
            return $discussion->participants->contains('id', $user->id);
        }

        return $discussion->participants()->whereKey($user->id)->exists();
    }
}
