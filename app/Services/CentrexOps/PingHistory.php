<?php

namespace App\Services\CentrexOps;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PingHistory
{
    public const EMPTY_DAY = 1440;

    public static function save(string $key, string $ip, CarbonImmutable $at, string $result): bool
    {
        if (!in_array($result, ['1', '0'], true)) {
            return false;
        }

        $date = $at->utc()->format('Y-m-d');
        $slot = (int) $at->utc()->format('H') * 60 + (int) $at->utc()->format('i');

        return DB::transaction(function () use ($key, $ip, $date, $slot, $result): bool {
            $query = DB::table('centrex_hr_ping_days')
                ->where('inventory_key', $key)->where('ip', $ip)->where('day', $date)
                ->lockForUpdate();
            $row = $query->first(['id', 'samples']);
            if (!$row) {
                DB::table('centrex_hr_ping_days')->insertOrIgnore([
                    'inventory_key' => $key, 'ip' => $ip, 'day' => $date,
                    'samples' => str_repeat('?', self::EMPTY_DAY),
                ]);
                $row = $query->first(['id', 'samples']);
            }
            if (!$row || !is_string($row->samples) || strlen($row->samples) !== self::EMPTY_DAY) {
                throw new \UnexpectedValueException('Historique de ping illisible.');
            }
            if ($row->samples[$slot] !== '?') {
                return false; // Une minute déjà mesurée ne change pas après coup.
            }
            $samples = $row->samples;
            $samples[$slot] = $result;
            DB::table('centrex_hr_ping_days')->where('id', $row->id)->update(['samples' => $samples]);
            return true;
        });
    }

    public static function dashboard(string $key, ?string $ip, CarbonImmutable $now): array
    {
        $now = $now->utc()->startOfMinute();
        $rows = !$ip ? collect() : DB::table('centrex_hr_ping_days')
            ->where('inventory_key', $key)->where('ip', $ip)
            ->where('day', '>=', $now->subDays(31)->format('Y-m-d'))
            ->pluck('samples', 'day');
        $lookup = function (CarbonImmutable $minute) use ($rows): string {
            $samples = $rows->get($minute->format('Y-m-d'));
            $slot = (int) $minute->format('H') * 60 + (int) $minute->format('i');
            return is_string($samples) && strlen($samples) === self::EMPTY_DAY
                ? $samples[$slot] : '?';
        };

        $lastAt = null;
        $current = null;
        for ($i = 0; $i < 3; ++$i) {
            $at = $now->subMinutes($i);
            $sample = $lookup($at);
            if ($sample === '1' || $sample === '0') {
                $lastAt = $at;
                $current = $sample === '1' ? 'up' : 'down';
                break;
            }
        }

        $periods = [];
        $up = 0;
        $down = 0;
        for ($i = 0; $i < 43200; ++$i) {
            $sample = $lookup($now->subMinutes($i));
            if ($sample === '1') {
                ++$up;
            } elseif ($sample === '0') {
                ++$down;
            }
            if (in_array($i + 1, [1440, 10080, 43200], true)) {
                $periods[match ($i + 1) { 1440 => '24h', 10080 => '7j', default => '30j' }] =
                    self::rates($up, $down, $i + 1);
            }
        }
        $recent = [];
        for ($i = 59; $i >= 0; --$i) {
            $at = $now->subMinutes($i);
            $sample = $lookup($at);
            $recent[] = [
                'status' => $sample === '1' ? 'up' : ($sample === '0' ? 'down' : 'unknown'),
                'at' => $at->timezone('Europe/Paris')->format('d/m/Y H:i'),
            ];
        }

        return ['current' => $current, 'last_at' => $lastAt, 'periods' => $periods, 'recent' => $recent];
    }

    /** Sept jours civils en Europe/Paris, du plus ancien à aujourd'hui. */
    public static function fleetWeek(array $machines, CarbonImmutable $now): array
    {
        if ($machines === []) {
            return [];
        }
        $nowUtc = $now->utc()->startOfMinute();
        $today = $nowUtc->timezone('Europe/Paris')->startOfDay();
        $oldest = $today->subDays(6)->addMinute()->utc();
        $keys = array_values(array_unique(array_column($machines, 'key')));
        $rows = DB::table('centrex_hr_ping_days')
            ->whereIn('inventory_key', $keys)
            ->whereBetween('day', [$oldest->format('Y-m-d'), $nowUtc->format('Y-m-d')])
            ->get(['inventory_key', 'ip', 'day', 'samples']);
        $samplesByMachine = [];
        foreach ($rows as $row) {
            if (is_string($row->samples) && strlen($row->samples) === self::EMPTY_DAY) {
                $samplesByMachine[$row->inventory_key][$row->ip][$row->day] = $row->samples;
            }
        }

        $week = [];
        foreach ($machines as $machine) {
            $days = [];
            for ($index = 0; $index < 7; ++$index) {
                $localDay = $today->subDays(6 - $index);
                $cursor = $localDay->addMinute()->utc(); // 00:01 locale incluse
                $end = $localDay->addDay()->utc(); // lendemain 00:00 exclu
                $ongoing = $index === 6;
                if ($ongoing && $nowUtc->addMinute()->lessThan($end)) {
                    $end = $nowUtc->addMinute();
                }
                $series = '';
                while ($cursor->lessThan($end)) {
                    $midnightUtc = $cursor->startOfDay()->addDay();
                    $until = $midnightUtc->lessThan($end) ? $midnightUtc : $end;
                    $length = intdiv($until->getTimestamp() - $cursor->getTimestamp(), 60);
                    $slot = (int) $cursor->format('H') * 60 + (int) $cursor->format('i');
                    $saved = $samplesByMachine[$machine['key']][$machine['ip'] ?? ''][$cursor->format('Y-m-d')] ?? null;
                    $series .= is_string($saved) ? substr($saved, $slot, $length) : str_repeat('?', $length);
                    $cursor = $until;
                }
                $days[] = self::classifyDay($series, $ongoing) + [
                    'date' => $localDay->format('d/m/Y'),
                    'weekday' => ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][(int) $localDay->format('w')],
                    'ongoing' => $ongoing,
                ];
            }
            $week[$machine['key']] = $days;
        }
        return $week;
    }

    /** Une absence de mesure ne peut pas rendre une journée entièrement verte. */
    public static function classifyDay(string $samples, bool $ongoing): array
    {
        if (preg_match('/[^?01]/', $samples)) {
            throw new \InvalidArgumentException('Échantillons ICMP invalides.');
        }
        $expected = strlen($samples);
        $up = substr_count($samples, '1');
        $down = substr_count($samples, '0');
        $measured = $up + $down;
        $status = match (true) {
            str_contains($samples, '000') => 'incident',
            $down > 0 => 'watch',
            $measured === 0 => 'unknown',
            $ongoing => 'partial',
            $measured === $expected => 'ok',
            default => 'unknown',
        };
        return [
            'status' => $status,
            'up' => $up, 'down' => $down, 'missing' => $expected - $measured,
            'expected' => $expected,
            'coverage' => $expected ? round(100 * $measured / $expected, 2) : 0,
        ];
    }

    private static function rates(int $up, int $down, int $expected): array
    {
        $measured = $up + $down;
        return [
            'rate' => $measured ? round(100 * $up / $measured, 2) : null,
            'coverage' => round(100 * $measured / $expected, 2),
            'up' => $up, 'down' => $down, 'expected' => $expected,
        ];
    }
}
