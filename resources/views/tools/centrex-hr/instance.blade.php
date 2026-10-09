@extends('layouts.app')

@section('title', 'Centrex-HR · '.$name)
@section('page-title', 'Outils')

@section('content')
@include('tools.centrex-hr._styles')
<div class="chr-app chr-sidebar-theme"><div class="ops-shell">
  <div class="ops-topbar">
    <a class="ops-brand" href="{{ route('tools.centrex_hr.index') }}"><span class="ops-brand-mark">HR</span><span>Centrex-HR<small>Parc téléphonique</small></span></a>
    <nav class="ops-nav" aria-label="Navigation Centrex">
      <a class="ops-back-button" href="{{ route('tools.centrex_hr.index') }}">← Retour à l’inventaire</a>
      <a href="{{ route('tools.centrex_hr.updates') }}">Mises à jour</a>
    </nav>
  </div>
  <header class="ops-hero">
    <div class="ops-hero-copy">
      <div class="ops-kicker">Fiche instance</div>
      <h1>{{ $name }}</h1>
    </div>
  </header>

  <section class="ops-grid" aria-label="Résumé de l’instance">
    <div class="ops-card"><span class="ops-eyebrow">ÉTAT OVH</span><h2><span class="ops-ovh-state {{ $state['tone'] }}">{{ $state['label'] }}</span></h2><p>Dernier relevé : {{ $machine->last_seen_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'inconnu' }}</p></div>
    <div class="ops-card"><span class="ops-eyebrow">ADRESSE ACTUELLE</span><h2>{{ $machine->ip ?: 'Non indiquée' }}</h2></div>
    <div class="ops-card"><span class="ops-eyebrow">ACCESSIBILITÉ ICMP</span>
      <h2><span class="ops-ovh-state {{ $ping['current'] === 'up' ? 'good' : ($ping['current'] === 'down' ? 'danger' : '') }}">{{ $ping['current'] === 'up' ? 'Répond au ping' : ($ping['current'] === 'down' ? 'Sans réponse' : 'État non déterminé') }}</span></h2>
      <p>Depuis le Hub · Dernier contrôle récent : {{ $ping['last_at']?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'aucun' }}. Un contrôle absent depuis trois minutes donne un état non déterminé.</p>
    </div>
    <div class="ops-card"><span class="ops-eyebrow">PING · 30 JOURS</span>
      <h2>{{ $ping['periods']['30j']['rate'] === null ? 'Non mesuré' : number_format($ping['periods']['30j']['rate'], 2, ',', ' ').' %' }}</h2>
      <p>{{ number_format($ping['periods']['30j']['up'], 0, ',', ' ') }} minutes avec réponse · Couverture {{ number_format($ping['periods']['30j']['coverage'], 2, ',', ' ') }} % des minutes attendues.</p>
    </div>
  </section>

  <section class="ops-panel ops-instance-panel" aria-labelledby="suivi-ping">
    <div class="ops-panel-heading"><div><span class="ops-eyebrow">SONDE DU HUB</span><h2 id="suivi-ping">Historique des pings</h2>
      <p>Une tentative par minute UTC, depuis le Hub vers l’IPv4 actuelle. Chaque ratio porte sur les contrôles effectivement réalisés ; la couverture indique les minutes qui ont été mesurées.</p></div></div>
    <div class="ops-ping-periods">
      @foreach(['24h' => '24 heures', '7j' => '7 jours', '30j' => '30 jours'] as $key => $label)
        @php($period = $ping['periods'][$key])
        <div class="ops-ping-period"><strong>{{ $label }}</strong>
          <span>{{ $period['rate'] === null ? 'Non mesuré' : number_format($period['rate'], 2, ',', ' ').' % de pings réussis' }}</span>
          <small>{{ $period['up'] }} min avec réponse · {{ $period['down'] }} min sans réponse · {{ number_format($period['coverage'], 2, ',', ' ') }} % de couverture</small>
        </div>
      @endforeach
    </div>
    <h3 class="ops-ping-timeline-title">Les 60 dernières minutes <small>de gauche à droite, la plus récente à droite</small></h3>
    <div class="ops-ping-scroll" role="region" aria-label="Contrôles des 60 dernières minutes" tabindex="0">
      <div class="ops-ping-bars" role="img" aria-label="Une barre par minute : vert avec réponse, rouge sans réponse, gris non mesuré">
        @foreach($ping['recent'] as $minute)
          <span class="ops-ping-minute {{ $minute['status'] }}" title="{{ $minute['at'] }} · {{ $minute['status'] === 'up' ? 'Réponse ICMP' : ($minute['status'] === 'down' ? 'Sans réponse' : 'Non mesuré') }}"></span>
        @endforeach
      </div>
    </div>
    <p class="ops-ovh-hint">Vert : réponse ICMP · Rouge : aucune réponse · Gris : contrôle absent ou erreur de sonde. Au démarrage, les minutes précédentes restent grises. Un ping ne vérifie ni Asterisk, ni les appels, ni l’audio. Si l’IP change, le suivi repart pour la nouvelle IP.</p>
  </section>

  <section class="ops-panel ops-instance-panel" aria-labelledby="historique-instance">
    <div class="ops-panel-heading"><div><span class="ops-eyebrow">SUIVI TECHNIQUE</span><h2 id="historique-instance">Historique des mises à jour</h2>
      <p>Campagnes rapprochées de manière unique avec l’adresse IP actuelle {{ $machine->ip ?: 'non indiquée' }}.</p></div></div>
    <p class="ops-note ops-warn">Rapprochement indicatif : les anciens rapports ne contiennent pas l’identifiant OVH. Un changement ou une réattribution d’IP peut masquer ou attribuer à tort des campagnes. Une migration d’identité sera nécessaire pour fiabiliser l’historique.</p>
    @if($history && $history->count())
      <div class="ops-table-scroll" role="region" aria-label="Campagnes de l’instance" tabindex="0">
        <table class="ops-table ops-instance-history"><thead><tr><th>Date</th><th>Campagne</th><th>Résultat</th><th>Composants relevés</th></tr></thead><tbody>
          @foreach($history as $result)
            <tr>
              <td>{{ ($result->campaign->finished_at ?? $result->campaign->started_at ?? $result->created_at)?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'inconnue' }}</td>
              <td><a href="{{ route('tools.centrex_hr.campaign', ['campaign' => $result->campaign_id]) }}">{{ $result->campaign->operation_label ?: $result->campaign->operation }}</a></td>
              <td><span class="ops-badge {{ $result->status === 'success' ? 'ok' : ($result->status === 'failure' ? 'bad' : 'attention') }}">{{ $result->status === 'success' ? 'Réussite' : ($result->status === 'failure' ? 'Échec' : $result->status) }}</span></td>
              <td>@forelse($result->steps as $step)<span class="ops-badge {{ $step->status === 'success' ? 'ok' : ($step->status === 'failure' ? 'bad' : 'attention') }}">{{ \App\Services\CentrexOps\CampaignComponents::LABELS[$step->component] ?? $step->component }} : {{ $step->version ?: $step->status }}</span>@empty<span class="ops-muted">Non détaillé</span>@endforelse</td>
            </tr>
          @endforeach
        </tbody></table>
      </div>
      {{ $history->links() }}
    @else
      <p class="ops-note">Aucune campagne rapprochée avec cette IP actuelle.</p>
    @endif
  </section>
  <footer class="ops-footer">HR Télécoms · Centrex-HR</footer>
</div></div>
@endsection
