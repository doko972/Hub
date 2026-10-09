<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\CentrexCampaign;
use App\Models\CentrexCampaignResult;
use App\Models\CentrexCampaignStep;
use App\Models\CentrexHrOvhMachine;
use App\Models\PbxServer;
use App\Services\CentrexOps\OvhSnapshot;
use App\Services\CentrexOps\OvhFleet;
use App\Services\CentrexOps\CampaignComponents;
use App\Services\CentrexOps\PingHistory;
use App\Services\OvhInventory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;
use Carbon\CarbonImmutable;

class CentrexHrDashboardController extends Controller
{
    public function index(Request $request)
    {
        $rawSearch = $request->query('q', '');
        $search = is_string($rawSearch) ? trim($rawSearch) : '';
        $search = mb_substr($search, 0, 80);
        $rawSort = $request->query('tri', 'nom');
        $sort = is_string($rawSort) && in_array($rawSort, ['nom', 'date_recent', 'date_ancien'], true)
            ? $rawSort : 'nom';
        $rawSize = $request->query('taille', '50');
        $size = is_string($rawSize) && in_array($rawSize, ['50', '100', '200', '500', 'all'], true)
            ? $rawSize : '50';

        // Les recherches, le tri et les changements de page ne contactent
        // jamais OVH : seule l'action POST d'actualisation le fait.
        $machines = CentrexHrOvhMachine::query()->where('is_present', true)
            ->get(['inventory_key', 'name', 'ip', 'ovh_state', 'ovh_zone', 'first_seen_at', 'last_seen_at']);
        $lastRefresh = $machines->max('last_seen_at');
        $suggestions = $machines->map(fn (CentrexHrOvhMachine $machine): string =>
            PbxServer::nomLisible($machine->name)
        )->filter(fn (string $name): bool => $name !== '')
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()->all();
        $visible = $machines->map(fn (CentrexHrOvhMachine $machine): array => [
            'key' => $machine->inventory_key,
            'name' => PbxServer::nomLisible($machine->name),
            'ip' => $machine->ip,
            'state' => OvhFleet::displayState($machine->ovh_state),
            'zone' => $machine->ovh_zone,
            'first_seen_at' => $machine->first_seen_at,
        ])->filter(fn (array $machine): bool => $search === '' ||
            mb_stripos($machine['name'], $search) !== false ||
            ($machine['ip'] !== null && mb_stripos($machine['ip'], $search) !== false)
        );
        $visible = match ($sort) {
            'date_recent' => $visible->sort(fn (array $a, array $b): int =>
                $b['first_seen_at']->getTimestamp() <=> $a['first_seen_at']->getTimestamp()
                ?: strcmp($a['key'], $b['key'])),
            'date_ancien' => $visible->sort(fn (array $a, array $b): int =>
                $a['first_seen_at']->getTimestamp() <=> $b['first_seen_at']->getTimestamp()
                ?: strcmp($a['key'], $b['key'])),
            default => $visible->sortBy(fn (array $machine): string =>
                mb_strtolower($machine['name']) . "\0" . $machine['key']),
        };
        $visible = $visible->values();
        $requestedPage = filter_var($request->query('page'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $perPage = $size === 'all' ? max(1, $visible->count()) : (int) $size;
        $pageNumber = $size === 'all' ? 1 : min(
            $requestedPage ?: 1,
            max(1, (int) ceil($visible->count() / $perPage))
        );
        $page = new LengthAwarePaginator(
            $visible->forPage($pageNumber, $perPage)->values(),
            $visible->count(), $perPage, $pageNumber,
            [
                'path' => route('tools.centrex_hr.index'),
                'query' => ['q' => $search, 'tri' => $sort, 'taille' => $size],
            ]
        );
        $pingWeek = PingHistory::fleetWeek($page->items(), CarbonImmutable::now('UTC'));

        return view('tools.centrex-hr.index', compact(
            'search', 'sort', 'size', 'page', 'lastRefresh', 'suggestions', 'pingWeek'
        ));
    }

    public function refresh(OvhInventory $ovh): RedirectResponse
    {
        if (!OvhInventory::isConfigured()) {
            return redirect()->route('tools.centrex_hr.index')
                ->with('inventory_error', 'L’accès OVH n’est pas configuré dans le Hub. La liste enregistrée est conservée.');
        }

        try {
            $inventory = $ovh->machines(true);
            if (!is_array($inventory)) {
                throw new UnexpectedValueException('La réponse OVH est illisible. La liste enregistrée a été conservée.');
            }
            $now = now()->toDateTimeString();
            $known = CentrexHrOvhMachine::query()->pluck('first_seen_at', 'inventory_key')
                ->map(fn ($value): string => (string) $value)->all();
            $rows = OvhSnapshot::prepare($inventory, $known, $now);
            $previousCount = CentrexHrOvhMachine::query()->where('is_present', true)->count();
            OvhSnapshot::assertPlausibleCount($previousCount, count($rows));
            $keys = array_column($rows, 'inventory_key');
            DB::transaction(function () use ($rows, $keys, $now): void {
                CentrexHrOvhMachine::query()->where('is_present', true)
                    ->whereNotIn('inventory_key', $keys)
                    ->update(['is_present' => false, 'updated_at' => $now]);
                CentrexHrOvhMachine::query()->upsert($rows, ['inventory_key'], [
                    'name', 'ip', 'ovh_ref', 'ovh_project', 'ovh_state', 'ovh_zone', 'is_present',
                    'last_seen_at', 'updated_at',
                ]);
            });

            return redirect()->route('tools.centrex_hr.index')
                ->with('inventory_notice', count($rows).' machines enregistrées depuis OVH.');
        } catch (UnexpectedValueException $error) {
            Log::warning('Centrex-HR : lecture OVH refusée', ['type' => get_class($error)]);
            return redirect()->route('tools.centrex_hr.index')
                ->with('inventory_error', $error->getMessage());
        } catch (Throwable $error) {
            Log::warning('Centrex-HR : lecture OVH impossible', ['type' => get_class($error)]);
            return redirect()->route('tools.centrex_hr.index')
                ->with('inventory_error', 'La mise à jour OVH a échoué. La liste enregistrée est conservée.');
        }
    }

    public function instance(string $inventoryKey)
    {
        $machine = CentrexHrOvhMachine::query()->where('inventory_key', $inventoryKey)->firstOrFail();
        $name = PbxServer::nomLisible($machine->name);
        $state = OvhFleet::displayState($machine->ovh_state);
        $ping = PingHistory::dashboard($machine->inventory_key, $machine->ip, CarbonImmutable::now('UTC'));

        // Les résultats historiques ne portent pas la référence OVH. Une IP
        // actuelle est un rapprochement indicatif, pas une identité durable.
        $history = $machine->ip && filter_var($machine->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? CentrexCampaignResult::query()->with(['campaign', 'steps'])
                ->where('ip', $machine->ip)
                ->whereHas('campaign')
                ->where('matching_state', 'unique')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(20)
            : null;

        return view('tools.centrex-hr.instance', compact('machine', 'name', 'state', 'history', 'ping'));
    }

    public function show(Request $request, CentrexCampaign $campaign)
    {
        $rawFilter = $request->query('etat', 'tous');
        $filter = is_string($rawFilter) ? $rawFilter : 'tous';
        if (!in_array($filter, ['tous', 'echec', 'reussite', 'a_rapprocher'], true)) {
            $filter = 'tous';
        }
        $results = CentrexCampaignResult::query()
            ->with(['pbxServer:id,name', 'steps'])
            ->where('campaign_id', $campaign->id)
            ->when($filter === 'echec', fn ($q) => $q->where('status', 'failure'))
            ->when($filter === 'reussite', fn ($q) => $q->where('status', 'success'))
            ->when($filter === 'a_rapprocher', fn ($q) => $q->where('matching_state', '!=', 'unique'))
            ->orderBy('ip')
            ->paginate(50)
            ->withQueryString();

        $componentLabels = CampaignComponents::LABELS;
        return view('tools.centrex-hr.show', compact('campaign', 'results', 'filter', 'componentLabels'));
    }

    public function updates(Request $request)
    {
        $latest = CentrexCampaign::query()->latest('created_at')->first();
        $campaignCount = CentrexCampaign::query()->count();
        $trackedSteps = CentrexCampaignStep::query()->count();
        $recentCampaigns = CentrexCampaign::query()->latest('created_at')->limit(8)->get();
        $componentSummary = [];
        if ($latest?->component_versions !== null) {
            $counts = CentrexCampaignStep::query()
                ->selectRaw('component, status, COUNT(*) AS total')
                ->whereHas('result', fn ($q) => $q->where('campaign_id', $latest->id))
                ->groupBy('component', 'status')->get();
            foreach (CampaignComponents::selected($latest->operation) as $key) {
                $byStatus = $counts->where('component', $key)->pluck('total', 'status');
                $success = (int) ($byStatus->get('success') ?? 0);
                $failure = (int) ($byStatus->get('failure') ?? 0);
                $reverted = (int) ($byStatus->get('reverted') ?? 0);
                $componentSummary[] = [
                    'key' => $key,
                    'label' => CampaignComponents::LABELS[$key],
                    'planned' => $latest->component_versions[$key] ?? null,
                    'version_type' => CampaignComponents::versionType($key),
                    'success' => $success,
                    'failure' => $failure,
                    'reverted' => $reverted,
                    'unconfirmed' => $latest->processed - $success - $failure - $reverted,
                ];
            }
        }

        $componentLabels = CampaignComponents::LABELS;
        $component = $request->query('module', 'tous');
        if (!is_string($component) || ($component !== 'tous' && !isset($componentLabels[$component]))) {
            $component = 'tous';
        }
        $status = $request->query('etat', 'success');
        if (!is_string($status) || !in_array($status, ['success', 'failure', 'unknown', 'reverted', 'tous'], true)) {
            $status = 'success';
        }
        $ip = $request->query('ip', '');
        $ip = is_string($ip) ? trim($ip) : '';
        $ipValid = $ip === '' || (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $version = $request->query('version', '');
        $version = is_string($version) ? trim($version) : '';
        $versionValid = $version === '' || (bool) preg_match('/^[0-9][0-9A-Za-z.+:~_-]{0,47}$/D', $version);
        $steps = CentrexCampaignStep::query()
            ->with(['result.campaign', 'result.pbxServer'])
            ->when($component !== 'tous', fn ($q) => $q->where('component', $component))
            ->when($status !== 'tous', fn ($q) => $q->where('status', $status))
            ->when($ip !== '' && $ipValid, fn ($q) => $q->whereHas('result', fn ($r) => $r->where('ip', $ip)))
            ->when($version !== '' && $versionValid, fn ($q) => $q->where('version', $version))
            ->when(!$ipValid || !$versionValid, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('observed_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('tools.centrex-hr.updates', compact(
            'latest', 'campaignCount', 'trackedSteps', 'recentCampaigns', 'componentSummary',
            'steps', 'componentLabels', 'component', 'status', 'ip', 'ipValid',
            'version', 'versionValid'
        ));
    }
}
