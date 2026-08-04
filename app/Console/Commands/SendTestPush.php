<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Console\Command;
use Minishlink\WebPush\WebPush;

/**
 * Envoie une notification de test et détaille la réponse de chaque service.
 *
 * Une notification qui n'arrive pas ne laisse aucune trace visible côté
 * utilisateur : c'est le service de push (Google, Mozilla, Apple) qui refuse,
 * silencieusement. Cette commande rend ce dialogue lisible.
 */
class SendTestPush extends Command
{
    protected $signature = 'push:test {email? : Destinataire ; à défaut, tous les abonnés}';

    protected $description = 'Envoie une notification push de test et affiche le détail des réponses';

    public function handle(): int
    {
        if (!PushService::isConfigured()) {
            $this->error('Aucune clé VAPID configurée. Voir « php artisan push:vapid-keys ».');

            return self::FAILURE;
        }

        $abonnements = $this->subscriptions();

        if ($abonnements->isEmpty()) {
            $this->warn('Aucun abonnement enregistré.');
            $this->line('Chaque personne doit cliquer une fois sur « Activer les notifications ».');

            return self::SUCCESS;
        }

        $this->info($abonnements->count() . ' abonnement(s) à tester…');
        $this->newLine();

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject') ?: config('app.url'),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        $charge = json_encode([
            'title' => 'Test du Hub',
            'body'  => 'Si vous voyez ceci, les notifications fonctionnent.',
            'url'   => '/messages',
            'icon'  => '/icon-192x192.png',
        ]);

        $service = app(PushService::class);

        foreach ($abonnements as $abonnement) {
            $webPush->queueNotification($service->toSubscription($abonnement), $charge);
        }

        $succes = 0;
        $echecs = 0;

        foreach ($webPush->flush() as $rapport) {
            $endpoint = $this->shorten($rapport->getEndpoint());

            if ($rapport->isSuccess()) {
                $succes++;
                $this->line("  <fg=green>✓</> {$endpoint}");
                continue;
            }

            $echecs++;
            $statut = $rapport->getResponse()?->getStatusCode() ?? '—';

            $this->line("  <fg=red>✗</> {$endpoint}");
            $this->line("     statut : {$statut}");
            $this->line('     motif  : ' . trim($rapport->getReason()));
            $this->line('     ' . $this->explain((int) $statut));
        }

        $this->newLine();
        $this->info("Réussites : {$succes}   Échecs : {$echecs}");

        return $echecs > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function subscriptions()
    {
        $email = $this->argument('email');

        if (!$email) {
            return PushSubscription::with('user')->get();
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Aucun compte pour « {$email} ».");

            return collect();
        }

        return PushSubscription::where('user_id', $user->id)->get();
    }

    /**
     * Traduit les codes que renvoient réellement les services de push.
     */
    private function explain(int $statut): string
    {
        return match (true) {
            $statut === 403 => 'Signature VAPID refusée : les clés ont changé depuis l\'abonnement. '
                             . 'L\'abonnement vient d\'être supprimé, la personne doit se réabonner.',
            $statut === 404,
            $statut === 410 => 'Abonnement expiré ou révoqué (navigateur réinstallé, données effacées). '
                             . 'Supprimé, réabonnement nécessaire.',
            $statut === 413 => 'Charge utile trop volumineuse.',
            $statut === 429 => 'Trop de notifications envoyées : le service temporise.',
            $statut >= 500  => 'Panne côté service de push, réessayer plus tard.',
            default         => 'Consultez le motif ci-dessus.',
        };
    }

    private function shorten(string $endpoint): string
    {
        $hote = parse_url($endpoint, PHP_URL_HOST) ?: 'inconnu';

        return $hote . ' …' . substr($endpoint, -12);
    }
}
