<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Comptage des messages non lus.
 *
 * Un message est non lu s'il a été écrit par quelqu'un d'autre après la
 * frontière de lecture du participant (discussion_user.last_read_at).
 *
 * Requêtes écrites en SQL plutôt qu'en Eloquent : un seul aller-retour, quel
 * que soit le nombre de fils, là où un parcours de collection en produirait un
 * par discussion.
 */
class Unread
{
    /**
     * Total tous fils confondus, pour la pastille de la sidebar.
     */
    public static function totalFor(int $userId): int
    {
        return self::baseQuery($userId)->count();
    }

    /**
     * Détail par discussion : [discussion_id => nombre].
     *
     * @return array<int, int>
     */
    public static function perDiscussionFor(int $userId): array
    {
        return self::baseQuery($userId)
            ->select('m.discussion_id', DB::raw('COUNT(*) as total'))
            ->groupBy('m.discussion_id')
            ->pluck('total', 'm.discussion_id')
            ->all();
    }

    private static function baseQuery(int $userId)
    {
        return DB::table('discussion_messages as m')
            ->join('discussion_user as p', function ($join) use ($userId) {
                $join->on('p.discussion_id', '=', 'm.discussion_id')
                     ->where('p.user_id', '=', $userId);
            })
            // Ses propres messages ne sont jamais « non lus ».
            ->where(function ($query) use ($userId) {
                $query->whereNull('m.user_id')->orWhere('m.user_id', '!=', $userId);
            })
            ->where(function ($query) {
                $query->whereNull('p.last_read_at')
                      ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            });
    }
}
