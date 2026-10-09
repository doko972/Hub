@extends('layouts.app')

@section('title', 'Centrex-HR')
@section('page-title', 'Outils')

@section('content')
<div class="chr-app">
<div class="ops-shell">
  <div class="ops-topbar">
    <a class="ops-brand" href="{{ route('tools.centrex_hr.index') }}" aria-label="Centrex-HR, accueil"><span class="ops-brand-mark">HR</span><span>Centrex-HR<small>Parc téléphonique</small></span></a>
    <nav class="ops-nav" aria-label="Navigation Centrex">
      <a class="active" aria-current="page" href="{{ route('tools.centrex_hr.index') }}">Inventaire</a>
      <a href="{{ route('tools.centrex_hr.updates') }}">Mises à jour</a>
      <a href="{{ url('/tools/centrex') }}">Centrex actuel ↗</a>
    </nav>
  </div>
  <header class="ops-hero ops-hero-fleet">
    <div class="ops-hero-copy">
      <div class="ops-kicker"><span class="ops-pulse" aria-hidden="true"></span> Espace gestion - suivi du parc Centrex</div>
      <h1>Centrex-HR <span>Inventaire du parc</span></h1>
      <p>Retrouvez vos Centrex, leurs adresses et les accès rapides. La liste enregistrée se met à jour quand vous le décidez.</p>
    </div>
  </header>

  <section class="ops-panel ops-fleet-panel" aria-labelledby="liste-parc">
    <div class="ops-panel-heading">
      <div><span class="ops-eyebrow">PARC CENTREX</span><h2 id="liste-parc">Vos machines</h2><p>Recherche et classement sur la dernière liste enregistrée.</p></div>
      @php($resultLabel = $search !== '' ? ($page->total() === 1 ? 'résultat' : 'résultats') : ($page->total() === 1 ? 'machine' : 'machines'))
      <span class="ops-total" aria-label="{{ $page->total() }} {{ $resultLabel }}">{{ $page->total() }} <small>{{ $resultLabel }}</small></span>
    </div>
    @if(session('inventory_error'))
      <p class="ops-note ops-warn" role="alert">{{ session('inventory_error') }}</p>
    @elseif(session('inventory_notice'))
      <p class="ops-note" role="status">{{ session('inventory_notice') }}</p>
    @endif
    @unless($lastRefresh)
      <p class="ops-note">Aucun inventaire enregistré pour l’instant. Cliquez sur « Actualiser OVH » pour créer la première liste.</p>
    @endunless
    <div class="ops-fleet-toolbar">
      <form id="chr-inventory-filters" class="ops-toolbar ops-fleet-filters" method="get" action="{{ route('tools.centrex_hr.index') }}">
        <div class="ops-search-field">
          <label for="chr-search">Rechercher</label>
          <div class="ops-search-control">
            <input id="chr-search" type="search" name="q" value="{{ $search }}" title="{{ $search }}" placeholder="Nom ou adresse IP"
                   autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"
                   aria-controls="chr-search-options">
            <a id="chr-search-clear" class="ops-search-clear" href="{{ route('tools.centrex_hr.index', ['tri' => $sort, 'taille' => $size]) }}"
               aria-label="Effacer la recherche et afficher tout le parc" @if($search === '') hidden @endif>× Effacer</a>
          </div>
          <div id="chr-search-options" class="ops-suggestions" role="listbox" aria-label="Machines proposées" hidden></div>
        </div>
        <label>Trier par
          <select name="tri">
            <option value="nom" @selected($sort === 'nom')>Nom de machine (A à Z)</option>
            <option value="date_recent" @selected($sort === 'date_recent')>Date d’ajout (récent)</option>
            <option value="date_ancien" @selected($sort === 'date_ancien')>Date d’ajout (ancien)</option>
          </select>
        </label>
        <label>Afficher
          <select name="taille">
            @foreach(['50', '100', '200', '500'] as $choice)
              <option value="{{ $choice }}" @selected($size === $choice)>{{ $choice }}</option>
            @endforeach
            <option value="all" @selected($size === 'all')>Toutes</option>
          </select>
        </label>
      </form>
      <div id="chr-search-data" data-candidates="{{ json_encode($suggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}" hidden></div>
      <div class="ops-sync">
        <form method="post" action="{{ route('tools.centrex_hr.refresh') }}">
          @csrf
          <button class="ops-action ops-refresh" type="submit">Actualiser OVH ↻</button>
        </form>
        <span>Dernière actualisation : <strong>{{ $lastRefresh?->timezone('Europe/Paris')->format('d/m/Y à H:i') ?? 'jamais' }}</strong></span>
      </div>
    </div>
    @if($sort !== 'nom')
      <p class="ops-date-hint">« Date d’ajout » correspond à la première apparition dans Centrex-HR, pas à la création de la machine chez OVH.</p>
    @endif
    <div class="ops-table-scroll" role="region" aria-label="Inventaire des Centrex" tabindex="0">
    <table class="ops-table ops-fleet-table">
      <colgroup><col class="ops-instance-column"><col class="ops-ip-column"><col class="ops-state-column"><col class="ops-week-column"></colgroup>
      <thead><tr><th>Instance</th><th>IPv4 publique</th><th>État OVH</th><th>Ping · 7 jours</th></tr></thead>
      <tbody>
      @forelse($page as $machine)
        <tr>
          <td data-label="Instance"><a class="ops-main ops-instance-link" href="{{ route('tools.centrex_hr.instance', ['inventoryKey' => $machine['key']]) }}" title="Ouvrir la fiche de {{ $machine['name'] }}">{{ $machine['name'] }}</a>
            @if($sort !== 'nom')<span class="ops-machine-date">Ajouté le {{ $machine['first_seen_at']->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</span>@endif
          </td>
          <td data-label="IPv4 publique">
            @if($machine['ip'])
              <div class="ops-ip-content">
                <div class="ops-ip-actions">
                  <a class="ops-ip-link" href="http://{{ $machine['ip'] }}/" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir le Centrex {{ $machine['ip'] }} dans un nouvel onglet"><code>{{ $machine['ip'] }}</code></a>
                  <button class="ops-copy" type="button" data-copy-kind="ip" data-copy-ip="{{ $machine['ip'] }}" aria-label="Copier l’adresse IP {{ $machine['ip'] }}">Copier IP</button>
                  <button class="ops-copy" type="button" data-copy-kind="ssh" data-copy-ip="{{ $machine['ip'] }}" aria-label="Copier la commande SSH pour {{ $machine['ip'] }}" title="Copier ssh -p 45222 a2dmin@{{ $machine['ip'] }}">Copier SSH</button>
                </div>
                <span class="ops-copy-status" role="status" aria-live="polite"></span>
                <input class="ops-manual-copy" type="text" readonly hidden aria-label="Texte à copier manuellement">
              </div>
            @else
              <span class="ops-muted">Non disponible</span>
            @endif
          </td>
          <td data-label="État OVH"><span class="ops-ovh-state {{ $machine['state']['tone'] }}">{{ $machine['state']['label'] }}</span></td>
          <td data-label="Ping · 7 jours">
            <div class="ops-ping-week" role="group" aria-label="Réponses ICMP sur les sept derniers jours, du plus ancien à aujourd’hui">
              @foreach($pingWeek[$machine['key']] ?? [] as $day)
                @php($description = match($day['status']) {
                  'ok' => 'Jour complet : 100 % de réponses et de contrôles',
                  'partial' => 'Jour en cours : réponses observées, bilan provisoire',
                  'watch' => 'Attention : contrôles sans réponse, sans série de trois minutes consécutives',
                  'incident' => 'Incident : au moins trois minutes consécutives sans réponse',
                  default => 'Non déterminé : contrôles absents ou incomplets',
                })
                <span class="ops-ping-week-day" role="img" title="{{ $day['weekday'] }} {{ $day['date'] }} · {{ $description }} · {{ $day['up'] }} réponses, {{ $day['down'] }} sans réponse, {{ $day['missing'] }} non mesurées sur {{ $day['expected'] }} minutes ({{ number_format($day['coverage'], 2, ',', ' ') }} % de couverture)" aria-label="{{ $day['weekday'] }} {{ $day['date'] }} : {{ $description }}">
                  <span class="ops-ping-week-square {{ $day['status'] }}" aria-hidden="true"></span>
                  <small aria-hidden="true">{{ $day['weekday'] }}</small>
                </span>
              @endforeach
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="4">{{ $lastRefresh ? 'Aucun Centrex ne correspond à cette recherche.' : 'Aucune liste encore enregistrée.' }}</td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    <div class="ops-ping-week-legend" aria-label="Légende des pings">
      <span><i class="ok"></i> Jour complet à 100 %</span><span><i class="partial"></i> Aujourd’hui, en cours</span>
      <span><i class="watch"></i> Échecs sans série de 3</span><span><i class="incident"></i> 3 échecs consécutifs</span>
      <span><i class="unknown"></i> Non déterminé</span>
    </div>
    <p class="ops-ovh-hint">Sept jours civils en heure de Paris, du plus ancien à aujourd’hui. Un jour terminé n’est vert que si chaque minute de 00:01 à 23:59 a répondu au ping. Survolez un carré pour voir ses chiffres. Mesure ICMP depuis le Hub ; elle ne certifie pas les appels.</p>
    @include('tools.centrex-hr._pagination', ['paginator' => $page])
  </section>
  <footer class="ops-footer">HR Télécoms · Centrex-HR</footer>
</div>
</div>
<script src="{{ asset('centrex-hr/inventory.js') }}?v=20261004c" defer
        nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
@endsection
