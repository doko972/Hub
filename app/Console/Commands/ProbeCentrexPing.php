<?php

namespace App\Console\Commands;

use App\Models\CentrexHrOvhMachine;
use App\Services\CentrexOps\PingHistory;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class ProbeCentrexPing extends Command
{
    protected $signature = 'centrex:ping {--ip= : Tester une IPv4 du parc pendant le pilote} {--inventory-key= : Tester une instance par sa clé} {--all : Tester toutes les instances présentes}';
    protected $description = 'Mesurer la réponse ICMP des instances OVH sans lancer le contrôle depuis une page Web';

    public function handle(): int
    {
        $key = $this->option('inventory-key');
        $ipOption = $this->option('ip');
        $all = (bool) $this->option('all');
        if (((int) $all + (int) ($key !== null) + (int) ($ipOption !== null)) !== 1) {
            $this->error('Choisir exactement une option : --all, --ip=<IPv4> ou --inventory-key=<clé>.');
            return self::FAILURE;
        }
        if ($key !== null && (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key))) {
            $this->error('Clé d’inventaire invalide.');
            return self::FAILURE;
        }
        if ($ipOption !== null && (!is_string($ipOption) || !filter_var($ipOption, FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            $this->error('Adresse IPv4 publique invalide.');
            return self::FAILURE;
        }

        // Le cron est prévu sur une seule VM Hub. Le verrou protège également
        // les tests manuels et ne dépend pas du cache Laravel configuré.
        $lock = fopen(storage_path('framework/centrex-hr-ping.lock'), 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            $this->warn('Une mesure est déjà en cours ou le verrou est inaccessible.');
            return self::FAILURE;
        }
        try {
            return $this->runProbes($all, $key, $ipOption);
        } catch (Throwable $error) {
            Log::error('Centrex-HR : échec de la sonde ICMP', ['type' => get_class($error)]);
            $this->error('La collecte a échoué. Vérifier les journaux du Hub.');
            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runProbes(bool $all, ?string $key, ?string $ipOption): int
    {
        $binary = '/usr/bin/ping';
        if (!is_executable($binary)) {
            $this->error('/usr/bin/ping est absent ou non exécutable.');
            return self::FAILURE;
        }
        $selfTest = new Process([$binary, '-4', '-n', '-c', '1', '-W', '1', '127.0.0.1']);
        $selfTest->setTimeout(3);
        $selfTest->run();
        if ($selfTest->getExitCode() !== 0) {
            $this->error('La sonde ne parvient pas à effectuer un ping local. Aucun résultat n’a été enregistré.');
            return self::FAILURE;
        }

        $machines = CentrexHrOvhMachine::query()->where('is_present', true)
            ->when($key !== null, fn ($query) => $query->where('inventory_key', $key))
            ->when($ipOption !== null, fn ($query) => $query->where('ip', $ipOption))
            ->orderBy('inventory_key')->get(['inventory_key', 'ip']);
        if ($machines->isEmpty()) {
            $this->error('Aucune instance présente ne correspond à cette sélection.');
            return self::FAILURE;
        }
        if ($ipOption !== null && $machines->count() !== 1) {
            $this->error('Cette IP est partagée par plusieurs fiches. Utilisez --inventory-key.');
            return self::FAILURE;
        }

        $at = CarbonImmutable::now('UTC')->startOfMinute();
        $queue = [];
        foreach ($machines as $machine) {
            $ip = $machine->ip;
            // Pas de résolution DNS ni d'adresse privée issue d'une donnée distante.
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }
            $queue[] = ['key' => $machine->inventory_key, 'ip' => $ip];
        }
        $total = count($queue);
        if ($total === 0) {
            $this->error('Aucune IPv4 publique valide dans la sélection.');
            return self::FAILURE;
        }

        $active = [];
        $next = 0;
        $results = [];
        // 20 requêtes simultanées : environ 15 lots pour 290 instances.
        while ($next < $total || $active) {
            while ($next < $total && count($active) < 20) {
                $target = $queue[$next++];
                try {
                    $process = new Process([$binary, '-4', '-n', '-c', '1', '-W', '1', $target['ip']]);
                    $process->setTimeout(3);
                    $process->start();
                    $active[] = ['target' => $target, 'process' => $process];
                } catch (Throwable $error) {
                    Log::warning('Centrex-HR : lancement ping impossible', ['type' => get_class($error)]);
                    $results['unknown'] = ($results['unknown'] ?? 0) + 1;
                }
            }
            foreach ($active as $index => $item) {
                $process = $item['process'];
                try {
                    if ($process->isRunning()) {
                        $process->checkTimeout();
                        continue;
                    }
                    $code = $process->getExitCode();
                    $value = $code === 0 ? '1' : ($code === 1 ? '0' : '?');
                } catch (Throwable $error) {
                    $process->stop(0);
                    $value = '?';
                }
                unset($active[$index]);
                $results[$value === '1' ? 'up' : ($value === '0' ? 'down' : 'unknown')] =
                    ($results[$value === '1' ? 'up' : ($value === '0' ? 'down' : 'unknown')] ?? 0) + 1;
                if ($value !== '?') {
                    PingHistory::save($item['target']['key'], $item['target']['ip'], $at, $value);
                }
            }
            if ($active) usleep(50_000);
        }

        if ($all) {
            DB::table('centrex_hr_ping_days')->where('day', '<', $at->subDays(35)->format('Y-m-d'))->delete();
        }
        $this->info(sprintf('Ping UTC %s : %d réponses, %d sans réponse, %d non mesurés, %d IP ignorées.',
            $at->format('Y-m-d H:i'), $results['up'] ?? 0, $results['down'] ?? 0,
            $results['unknown'] ?? 0, $machines->count() - $total));
        if (($results['unknown'] ?? 0) === $total) {
            $this->error('Tous les contrôles ont échoué côté sonde ; aucun échec n’a été attribué aux machines.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
