<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => env('VAPID_SUBJECT', env('APP_URL')),
                'publicKey'  => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ]);
    }

    /**
     * Envoyer une notification push à un abonnement.
     */
    public function send(PushSubscription $sub, string $title, string $body, string $url = '/chat'): bool
    {
        $subscription = Subscription::create([
            'endpoint'        => $sub->endpoint,
            'publicKey'       => $sub->public_key,
            'authToken'       => $sub->auth_token,
            'contentEncoding' => 'aesgcm',
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/images/logo-192.png',
        ]);

        $report = $this->webPush->sendOneNotification($subscription, $payload);

        return $report->isSuccess();
    }

    /**
     * Envoyer une notification à tous les abonnements d'un utilisateur.
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '/chat'): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        foreach ($subscriptions as $sub) {
            try {
                $this->send($sub, $title, $body, $url);
            } catch (\Exception $e) {
                // Abonnement expiré ou invalide — on le supprime
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    $sub->delete();
                }
            }
        }
    }
}
