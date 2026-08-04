<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    private ?WebPush $webPush = null;

    /**
     * Les notifications ne sont possibles qu'avec une paire de clés VAPID.
     * Sans elles, l'application doit continuer à fonctionner normalement :
     * on se contente de ne rien envoyer (voir « php artisan push:vapid-keys »).
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    /**
     * Construction paresseuse : instancier WebPush sans clés lève une exception.
     */
    private function client(): WebPush
    {
        return $this->webPush ??= new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject') ?: config('app.url'),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }

    /**
     * Envoie une notification à tous les appareils d'un utilisateur.
     *
     * Les envois sont mis en file puis vidés d'un bloc : la librairie les
     * effectue alors en parallèle, au lieu d'un aller-retour HTTP par appareil.
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '/', array $options = []): void
    {
        if (!self::isConfigured()) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode(array_merge([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/icon-192x192.png',
            'badge' => '/icon-192x192.png',
        ], $options));

        try {
            $client = $this->client();

            foreach ($subscriptions as $sub) {
                $client->queueNotification($this->toSubscription($sub), $payload);
            }

            foreach ($client->flush() as $report) {
                $this->handleReport($report, $subscriptions);
            }
        } catch (\Throwable $e) {
            // Une notification perdue ne doit jamais faire échouer l'action qui
            // l'a déclenchée (l'envoi d'un message, par exemple).
            Log::warning('Envoi de notification push échoué', ['exception' => $e]);
        }
    }

    public function toSubscription(PushSubscription $sub): Subscription
    {
        return Subscription::create([
            'endpoint'  => $sub->endpoint,
            'publicKey' => $sub->public_key,
            'authToken' => $sub->auth_token,
            // Pas de contentEncoding forcé : la librairie retient « aes128gcm »,
            // le chiffrement normalisé. L'ancien « aesgcm » (brouillon 04) est
            // progressivement refusé par les services de push.
        ]);
    }

    /**
     * Purge les abonnements que le service de push déclare périmés.
     *
     * Sans cela, un navigateur désinstallé laisse un abonnement mort en base,
     * réessayé à chaque message.
     */
    private function handleReport($report, $subscriptions): void
    {
        if ($report->isSuccess()) {
            return;
        }

        $statut = $report->getResponse()?->getStatusCode();

        // 404/410 : abonnement disparu. 403 : signature VAPID refusée, ce qui
        // arrive quand la paire de clés a changé depuis l'abonnement — la ligne
        // ne redeviendra jamais valide, autant la retirer.
        if ($report->isSubscriptionExpired() || in_array($statut, [403, 404, 410], true)) {
            $subscriptions->firstWhere('endpoint', $report->getEndpoint())?->delete();

            Log::info('Abonnement push supprimé', [
                'endpoint' => $report->getEndpoint(),
                'status'   => $statut,
                'reason'   => $report->getReason(),
            ]);

            return;
        }

        Log::warning('Notification push refusée par le service', [
            'endpoint' => $report->getEndpoint(),
            'status'   => $statut,
            'reason'   => $report->getReason(),
        ]);
    }
}
