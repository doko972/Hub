<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid-keys';

    protected $description = 'Génère une paire de clés VAPID pour les notifications push';

    public function handle(): int
    {
        if (config('services.webpush.public_key')) {
            $this->warn('Des clés VAPID sont déjà configurées.');
            $this->line('Les remplacer invalidera tous les abonnements existants :');
            $this->line('chaque navigateur devra réactiver les notifications.');

            if (!$this->confirm('Générer une nouvelle paire malgré tout ?', false)) {
                return self::SUCCESS;
            }
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            return $this->expliquerEchec($e);
        }

        $this->newLine();
        $this->info('Clés générées. À copier dans votre fichier .env :');
        $this->newLine();
        $this->line('VAPID_SUBJECT="' . config('app.url') . '"');
        $this->line('VAPID_PUBLIC_KEY="' . $keys['publicKey'] . '"');
        $this->line('VAPID_PRIVATE_KEY="' . $keys['privateKey'] . '"');
        $this->newLine();
        $this->comment('La clé privée ne doit jamais être exposée ni versionnée.');
        $this->comment('Pensez à « php artisan config:clear » après modification.');

        return self::SUCCESS;
    }

    /**
     * La génération repose sur les courbes elliptiques d'OpenSSL. Sous Windows,
     * PHP ne trouve souvent pas openssl.cnf, et l'erreur brute n'aide en rien.
     */
    private function expliquerEchec(\Throwable $e): int
    {
        $this->error('Impossible de générer les clés : ' . $e->getMessage());
        $this->newLine();

        if (!getenv('OPENSSL_CONF')) {
            $this->warn("La variable d'environnement OPENSSL_CONF n'est pas définie.");
            $this->line("C'est la cause habituelle sous Windows : OpenSSL ne trouve pas sa configuration.");
            $this->newLine();

            foreach ([
                'C:\xampp\apache\conf\openssl.cnf',
                'C:\xampp\php\extras\openssl\openssl.cnf',
            ] as $chemin) {
                if (is_file($chemin)) {
                    $this->line('Fichier trouvé : ' . $chemin);
                    $this->line('Relancez la commande ainsi (PowerShell) :');
                    $this->line('    $env:OPENSSL_CONF = "' . $chemin . '"; php artisan push:vapid-keys');
                    $this->newLine();
                    break;
                }
            }
        }

        $this->line('Alternative, sans PHP :  npx web-push generate-vapid-keys');
        $this->line('Ou générez les clés directement sur le serveur de production.');

        return self::FAILURE;
    }
}
