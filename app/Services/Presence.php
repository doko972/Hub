<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Liste des collègues connectés, telle qu'affichée dans la sidebar.
 */
class Presence
{
    /**
     * Utilisateurs à afficher : les personnes en ligne d'abord, puis celles
     * vues récemment (grisées). Au-delà, la liste n'apporte plus rien.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function roster(?int $currentUserId = null): Collection
    {
        $onlineSince = now()->subMinutes((int) config('presence.online_within_minutes', 5));
        $recentSince = now()->subHours((int) config('presence.recent_within_hours', 24));

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $recentSince)
            ->orderByDesc('last_seen_at')
            ->limit((int) config('presence.max_users', 20))
            ->get(['id', 'name', 'avatar_path', 'last_seen_at'])
            ->map(fn (User $user) => [
                'id'        => $user->id,
                'name'      => $user->name,
                'initials'  => $user->initials(),
                'avatar'    => $user->avatarUrl(),
                'is_online' => $user->last_seen_at->gte($onlineSince),
                'is_self'   => $user->id === $currentUserId,
                // Rendu côté serveur : la locale de l'application est « en »,
                // alors que l'interface est en français.
                'seen_ago'  => $user->last_seen_at->locale('fr')->diffForHumans(),
            ])
            ->values();
    }

    /**
     * Nombre de personnes actuellement en ligne.
     */
    public static function onlineCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->where('last_seen_at', '>=', now()->subMinutes((int) config('presence.online_within_minutes', 5)))
            ->count();
    }
}
