<?php

namespace App\Policies;

use App\Models\DiscussionMessage;
use App\Models\User;

/**
 * On ne modifie et on ne supprime que ses propres messages.
 *
 * Volontairement sans exception pour les administrateurs : réécrire la parole
 * d'autrui dans une conversation privée ne relève pas de l'administration
 * technique. Une modération éventuelle mériterait sa propre traçabilité.
 */
class DiscussionMessagePolicy
{
    public function update(User $user, DiscussionMessage $message): bool
    {
        return $this->isAuthor($user, $message)
            && $this->isParticipant($user, $message);
    }

    public function delete(User $user, DiscussionMessage $message): bool
    {
        return $this->isAuthor($user, $message)
            && $this->isParticipant($user, $message);
    }

    private function isAuthor(User $user, DiscussionMessage $message): bool
    {
        return $message->user_id !== null && $message->user_id === $user->id;
    }

    /**
     * Avoir quitté un groupe retire aussi le droit d'y retoucher ses messages.
     */
    private function isParticipant(User $user, DiscussionMessage $message): bool
    {
        return $message->discussion?->participants()->whereKey($user->id)->exists() ?? false;
    }
}
