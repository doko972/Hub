@extends('layouts.app')

@section('title', 'Campagne — Centrex-HR')
@section('page-title', 'Outils')

@section('content')
@include('tools.centrex-hr._styles')
<div class="chr-app">
<div class="ops-shell">
  <div class="ops-topbar">
    <a class="ops-brand" href="{{ route('tools.centrex_hr.index') }}" aria-label="Centrex-HR, accueil"><span class="ops-brand-mark">HR</span><span>Centrex-HR<small>Parc téléphonique</small></span></a>
    <nav class="ops-nav" aria-label="Navigation Centrex">
      <a href="{{ route('tools.centrex_hr.index') }}">Inventaire</a>
      <a class="active" href="{{ route('tools.centrex_hr.updates') }}">Mises à jour</a>
      <a href="{{ url('/tools/centrex') }}">Centrex actuel ↗</a>
    </nav>
  </div>
  <header class="ops-hero">
    <div class="ops-hero-copy">
      <div class="ops-kicker"><a href="{{ route('tools.centrex_hr.updates') }}">← Mises à jour</a> · Rapport V{{ $campaign->script_version }}</div>
      <h1>Résultat de campagne</h1>
      <p>{{ $campaign->operation_label }}</p>
    </div>
    <div class="ops-hero-aside"><strong>{{ $campaign->processed }} Centrex traités</strong><span>{{ $campaign->succeeded }} réussites · {{ $campaign->failed }} échecs<br>{{ $campaign->finished_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'Date d’exécution inconnue' }}</span></div>
  </header>
  <section class="ops-panel">
    <h2>Ce que ce rapport permet de conclure</h2>
    <p class="ops-note">Ces états décrivent l'exécution de la mise à jour. Aucun contrôle d'appel entrant ou sortant n'est inclus.</p>
    @if(!$campaign->targets_complete)
      <p class="ops-note ops-warn">Rapport partiel : {{ $campaign->results()->count() }} résultat(s) individuel(s) conservé(s) sur {{ $campaign->processed }} Centrex traités. Les autres fiches ne peuvent pas recevoir d'état individuel à partir de ce fichier.</p>
    @endif
    @if(!$campaign->reboot_coverage_complete)
      <p class="ops-note ops-warn">Le bilan annonce {{ $campaign->reboot_flagged }} redémarrage(s) requis, mais ne permet pas d'exclure un redémarrage nécessaire sur les machines arrêtées en erreur avant le contrôle final.</p>
    @endif
    @if($campaign->component_versions === null)
      <p class="ops-note ops-warn">Ce bilan ancien ne détaille pas les modules : aucun succès individuel ne peut être déduit du résultat global.</p>
    @else
      <p><a class="ops-action" href="{{ route('tools.centrex_hr.updates') }}">Voir l’historique par module et Centrex →</a></p>
    @endif
  </section>
  <section class="ops-panel">
    <h2>Résultats individuels</h2>
    <form class="ops-toolbar" method="get" action="{{ route('tools.centrex_hr.campaign', $campaign) }}">
      <label>Afficher
        <select name="etat">
          <option value="tous" @selected($filter === 'tous')>Tous</option>
          <option value="echec" @selected($filter === 'echec')>Échecs</option>
          <option value="reussite" @selected($filter === 'reussite')>Réussites</option>
          <option value="a_rapprocher" @selected($filter === 'a_rapprocher')>À rapprocher du Hub</option>
        </select>
      </label>
      <button class="ops-btn" type="submit">Filtrer</button>
    </form>
    <table class="ops-table">
      <thead><tr><th>Adresse</th><th>Fiche Hub</th><th>Opération</th><th>Étapes confirmées</th><th>Détail</th></tr></thead>
      <tbody>
      @forelse($results as $result)
        <tr>
          <td data-label="Adresse"><code>{{ $result->ip }}</code></td>
          <td data-label="Fiche Hub">
            @if($result->matching_state === 'unique')
              {{ $result->pbxServer?->name ?? 'Fiche retirée depuis l’import' }}
            @elseif($result->matching_state === 'ambiguous')
              <span class="ops-badge attention">Plusieurs fiches : à rapprocher</span>
            @else
              <span class="ops-badge unknown">Aucune fiche correspondante</span>
            @endif
          </td>
          <td data-label="Opération">
            @if($result->status === 'success')
              <span class="ops-badge ok">Réussie</span>
            @else
              <span class="ops-badge bad">En échec{{ $result->code !== null ? ' · code '.$result->code : '' }}</span>
            @endif
            @if($result->reboot_required === true)
              <span class="ops-badge attention">Redémarrage signalé</span>
            @elseif($result->reboot_required === null)
              <span class="ops-muted">Redémarrage : non déterminé</span>
            @endif
          </td>
          <td data-label="Étapes confirmées">
            @forelse($result->steps as $step)
              <div class="ops-step">
                <span class="ops-badge {{ $step->status === 'success' ? 'ok' : ($step->status === 'failure' ? 'bad' : 'unknown') }}">{{ $componentLabels[$step->component] ?? $step->component }} : {{ $step->status === 'success' ? 'terminée' : ($step->status === 'failure' ? 'en échec' : ($step->status === 'reverted' ? 'version précédente restaurée' : 'issue inconnue')) }}</span>
                @if($step->status === 'success')
                  <span class="ops-muted">{{ $step->version }} · {{ $step->observed_at?->timezone('Europe/Paris')->format('d/m/Y H:i:s') }}</span>
                @endif
              </div>
            @empty
              <span class="ops-muted">Aucune étape confirmée dans ce bilan.</span>
            @endforelse
          </td>
          <td data-label="Détail">
            @if($result->message)<strong>{{ $result->message }}</strong>@endif
            @if($result->diagnostic)
              <details><summary>Voir le diagnostic rapporté</summary><div class="ops-detail">{{ $result->diagnostic }}</div></details>
            @elseif(!$result->message)
              <span class="ops-muted">Aucun détail supplémentaire.</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5">Aucun résultat individuel pour ce filtre.</td></tr>
      @endforelse
      </tbody>
    </table>
    <div class="ops-foot">{{ $results->total() }} ligne(s) détaillée(s) pour ce filtre.</div>
    @include('tools.centrex-hr._pagination', ['paginator' => $results])
  </section>
  <footer class="ops-footer">Centrex-HR · Rapport d'installation, sans mesure de disponibilité téléphonique</footer>
</div>
</div>
@endsection
