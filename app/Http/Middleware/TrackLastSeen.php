<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Met à jour la date de dernière activité de l'utilisateur connecté.
 *
 * Appliqué aux groupes web et api : l'application de bureau, qui s'authentifie
 * par jeton Sanctum sans session, compte donc elle aussi comme une présence.
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->touch($user->getKey());
        }

        return $next($request);
    }

    private function touch(int|string $userId): void
    {
        $key = "presence:last-seen:{$userId}";

        // Une écriture par minute au maximum : la requête de sondage revient
        // toutes les 30 s, et chaque navigation déclencherait sinon un UPDATE.
        if (Cache::has($key)) {
            return;
        }

        $minutes = max(1, (int) config('presence.write_every_minutes'));

        Cache::put($key, true, now()->addMinutes($minutes));

        // Passage par le query builder plutôt que par le modèle : cela évite de
        // toucher updated_at, qui ne reflète pas une modification du profil.
        DB::table('users')->where('id', $userId)->update(['last_seen_at' => now()]);
    }
}
