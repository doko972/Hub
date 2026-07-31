<?php

namespace App\Services;

use App\Models\Message;
use Carbon\Carbon;

/**
 * Quota journalier de génération d'images.
 *
 * Les images peuvent naître de deux chemins : la commande /imagine
 * (ImageController) et l'outil generate_image appelé par le modèle pendant une
 * conversation (ChatController). Le comptage doit couvrir les deux, sans quoi
 * il suffit de demander une image en langage naturel pour ignorer la limite.
 */
class ImageQuota
{
    public const DAILY_LIMIT = 20;

    /**
     * Nombre d'images générées aujourd'hui par l'utilisateur.
     */
    public static function usedToday(int $userId): int
    {
        $ownedByUser = fn ($query) => $query->where('user_id', $userId);

        // Chemin /imagine : un message utilisateur préfixé.
        $viaCommand = Message::whereHas('conversation', $ownedByUser)
            ->where('role', 'user')
            ->where('content', 'like', '/imagine %')
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Chemin outil : une réponse de l'assistant portant une image DALL-E.
        $viaTool = Message::whereHas('conversation', $ownedByUser)
            ->where('role', 'assistant')
            ->where('image_path', 'like', 'chat-images/%/dalle-%')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $viaCommand + $viaTool;
    }

    public static function remaining(int $userId): int
    {
        return max(0, self::DAILY_LIMIT - self::usedToday($userId));
    }

    public static function exceeded(int $userId): bool
    {
        return self::usedToday($userId) >= self::DAILY_LIMIT;
    }
}
