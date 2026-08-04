<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Qui est en train d'écrire, et où.
 *
 * L'information est volatile par nature : elle vit dans le cache, avec une
 * expiration courte. Aucune table, donc aucune ligne à purger — et si le
 * navigateur de quelqu'un se ferme brutalement, son état disparaît tout seul.
 */
class Typing
{
    /**
     * Durée pendant laquelle un signal reste valable. Doit dépasser l'intervalle
     * auquel le client le renouvelle, sinon l'indicateur clignoterait.
     */
    private static function ttl(): int
    {
        return max(3, (int) config('messaging.typing_ttl_seconds', 8));
    }

    private static function key(int $discussionId): string
    {
        return "typing:discussion:{$discussionId}";
    }

    /**
     * Signale que quelqu'un écrit dans un fil.
     */
    public static function mark(int $discussionId, int $userId, string $name): void
    {
        $etat = Cache::get(self::key($discussionId), []);

        $etat[$userId] = [
            'name'  => $name,
            'until' => now()->addSeconds(self::ttl())->timestamp,
        ];

        // La clé vit plus longtemps que les signaux qu'elle contient : le
        // filtrage à la lecture fait foi, la durée de la clé n'est qu'un
        // garde-fou pour que rien ne traîne indéfiniment.
        Cache::put(self::key($discussionId), $etat, now()->addSeconds(self::ttl() * 4));
    }

    /**
     * Retire immédiatement le signal — à l'envoi du message, ou quand le champ
     * est vidé. Sans cela, l'indicateur survivrait quelques secondes de trop.
     */
    public static function clear(int $discussionId, int $userId): void
    {
        $etat = Cache::get(self::key($discussionId), []);

        unset($etat[$userId]);

        if ($etat) {
            Cache::put(self::key($discussionId), $etat, now()->addSeconds(self::ttl() * 4));
        } else {
            Cache::forget(self::key($discussionId));
        }
    }

    /**
     * Noms des autres participants en train d'écrire.
     *
     * @return array<int, string>
     */
    public static function othersFor(int $discussionId, int $userId): array
    {
        $maintenant = now()->timestamp;

        return collect(Cache::get(self::key($discussionId), []))
            ->reject(fn ($signal, $id) => (int) $id === $userId)
            ->filter(fn ($signal) => ($signal['until'] ?? 0) > $maintenant)
            ->pluck('name')
            ->values()
            ->all();
    }
}
