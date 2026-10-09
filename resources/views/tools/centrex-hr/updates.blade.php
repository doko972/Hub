@extends('layouts.app')

@section('title', 'Mises à jour — Centrex-HR')
@section('page-title', 'Outils')

@section('content')
@include('tools.centrex-hr._styles')
<div class="chr-app chr-sidebar-theme"><div class="ops-shell">
  <div class="ops-topbar">
    <a class="ops-brand" href="{{ route('tools.centrex_hr.index') }}"><span class="ops-brand-mark">HR</span><span>Centrex-HR<small>Parc téléphonique</small></span></a>
    <nav class="ops-nav" aria-label="Navigation Centrex"><a href="{{ route('tools.centrex_hr.index') }}">Inventaire</a><a class="active" aria-current="page" href="{{ route('tools.centrex_hr.updates') }}">Mises à jour</a><a href="{{ url('/tools/centrex') }}">Centrex actuel ↗</a></nav>
  </div>
  <header class="ops-hero"><div class="ops-hero-copy">
    <div class="ops-kicker">Suivi des interventions</div>
    <h1>Historique <span>des mises à jour</span></h1>
    <p>Bilans, versions et dates enregistrés lors des campagnes importées.</p>
  </div></header>

  <section class="ops-grid" aria-label="Synthèse des mises à jour">
    <div class="ops-card"><div class="label">Étapes dans l'historique</div><div class="number">{{ $trackedSteps }}</div><div class="detail">{{ $campaignCount }} campagne(s) importée(s). Seules les étapes détaillées sont comptées.</div></div>
    <div class="ops-card"><div class="label">Centrex traités</div><div class="number">{{ $latest?->processed ?? '—' }}</div><div class="detail">Dernière campagne importée.</div></div>
    <div class="ops-card good"><div class="label">Opérations réussies</div><div class="number">{{ $latest?->succeeded ?? '—' }}</div><div class="detail">Résultat du dernier bilan.</div></div>
    <div class="ops-card danger"><div class="label">Opérations en échec</div><div class="number">{{ $latest?->failed ?? '—' }}</div><div class="detail">Résultat du dernier bilan, sans test d'appel.</div></div>
  </section>

  <section class="ops-panel">
    <h2>Rechercher une mise à jour</h2>
    <form class="ops-toolbar" method="get" action="{{ route('tools.centrex_hr.updates') }}">
      <label>Module ou opération<select name="module"><option value="tous" @selected($component === 'tous')>Tous</option>
        @foreach($componentLabels as $key => $label)
          <option value="{{ $key }}" @selected($component === $key)>{{ $label }}</option>
        @endforeach
      </select></label>
      <label>Résultat<select name="etat">
        <option value="success" @selected($status === 'success')>Terminée</option>
        <option value="failure" @selected($status === 'failure')>En échec</option>
        <option value="unknown" @selected($status === 'unknown')>Issue inconnue</option>
        <option value="reverted" @selected($status === 'reverted')>Version précédente restaurée</option>
        <option value="tous" @selected($status === 'tous')>Tous</option>
      </select></label>
      <label>Adresse IPv4<input name="ip" value="{{ $ip }}" placeholder="Ex. 192.0.2.10" inputmode="decimal"></label>
      <label>Version constatée<input name="version" value="{{ $version }}" placeholder="Ex. 21.2.0"></label>
      <button class="ops-btn" type="submit">Filtrer</button>
    </form>
    @if(!$ipValid)<p class="ops-note ops-warn" role="alert">Entrez une adresse IPv4 complète pour rechercher un Centrex.</p>@endif
    @if(!$versionValid)<p class="ops-note ops-warn" role="alert">Entrez une version valide, par exemple 21.2.0.</p>@endif
    <p class="ops-foot">Le rapprochement historique utilise l'adresse de la campagne. Si l'adresse OVH a changé ou a été partagée, vérifiez l'identité du Centrex avant d'attribuer un ancien bilan.</p>
    <table class="ops-table">
      <thead><tr><th>Centrex</th><th>Module ou opération</th><th>Résultat</th><th>Version</th><th>Date</th><th>Campagne</th></tr></thead>
      <tbody>
      @forelse($steps as $step)
        <tr>
          <td data-label="Centrex"><code>{{ $step->result->ip }}</code><br><span class="ops-muted">{{ $step->result->matching_state === 'unique' ? ($step->result->pbxServer?->name ?? 'Fiche retirée') : 'Fiche non attribuée' }}</span></td>
          <td data-label="Module ou opération"><strong>{{ $componentLabels[$step->component] ?? $step->component }}</strong></td>
          <td data-label="Résultat"><span class="ops-badge {{ $step->status === 'success' ? 'ok' : ($step->status === 'failure' ? 'bad' : 'unknown') }}">{{ $step->status === 'success' ? 'Terminée' : ($step->status === 'failure' ? 'En échec' : ($step->status === 'reverted' ? 'Version précédente restaurée' : 'Issue inconnue')) }}</span></td>
          <td data-label="Version">
            @if($step->status === 'success' && $step->version)
              {{ $step->version }}<br><span class="ops-muted">{{ \App\Services\CentrexOps\CampaignComponents::versionType($step->component) }}</span>
            @elseif($step->result->campaign->component_versions[$step->component] ?? null)
              <span class="ops-muted">Version prévue : {{ $step->result->campaign->component_versions[$step->component] }}</span>
            @else
              <span class="ops-muted">Non constatée</span>
            @endif
          </td>
          <td data-label="Date">{{ $step->observed_at?->timezone('Europe/Paris')->format('d/m/Y H:i:s') ?? 'Non connue' }}</td>
          <td data-label="Campagne"><a href="{{ route('tools.centrex_hr.campaign', $step->result->campaign) }}">{{ $step->result->campaign->operation }} · {{ $step->result->campaign->finished_at?->timezone('Europe/Paris')->format('d/m/Y') ?? 'sans date' }}</a></td>
        </tr>
      @empty
        <tr><td colspan="6">Aucun résultat détaillé ne correspond à ces filtres. Les anciens bilans ne renseignent pas les étapes individuelles.</td></tr>
      @endforelse
      </tbody>
    </table>
    <div class="ops-foot">{{ $steps->total() }} résultat(s) correspondant aux filtres.</div>
    @include('tools.centrex-hr._pagination', ['paginator' => $steps])
  </section>
  @if($latest)
    <section class="ops-panel" aria-labelledby="dernier-rapport">
      <h2 id="dernier-rapport">Dernier rapport importé</h2>
      <p><strong>{{ $latest->operation_label }}</strong> · script V{{ $latest->script_version }} ·
        campagne {{ $latest->finished_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'date d’exécution inconnue' }} ·
        import {{ $latest->created_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}.</p>
      @if(!$latest->targets_complete)
        <p class="ops-note ops-warn"><strong>Bilan partiel :</strong> le total de {{ $latest->succeeded }} réussites est connu, mais leurs adresses individuelles ne figurent pas toutes dans ce rapport.</p>
      @endif
      <p class="ops-note">Redémarrages signalés : {{ $latest->reboot_flagged }}.
        @if(!$latest->reboot_coverage_complete)
          Certains échecs n'ont pas atteint le contrôle final du redémarrage.
        @else
          L'état du redémarrage est connu pour tous les résultats détaillés.
        @endif
      </p>
      <a class="ops-action" href="{{ route('tools.centrex_hr.campaign', $latest) }}">Ouvrir le bilan complet →</a>
    </section>
    <section class="ops-panel" aria-labelledby="etapes-rapport">
      <h2 id="etapes-rapport">Mises à jour par composant</h2>
      @if($componentSummary === [])
        <p class="ops-note ops-warn">Ce bilan ancien ne contient pas le détail des étapes ni leurs versions individuelles. Son résultat global ne permet pas de déduire quels modules ont été installés.</p>
      @else
        <p class="ops-foot">Chiffres du dernier bilan uniquement. « Terminée » confirme que la procédure et ses contrôles ont abouti à cette date, même si le Centrex était déjà à jour. Cela ne vérifie pas la version présente aujourd’hui.</p>
        <div class="ops-components">
        @foreach($componentSummary as $component)
          <article class="ops-component">
            <h3>{{ $component['label'] }}</h3>
            <div class="ops-component-count"><strong>{{ $component['success'] }}</strong> terminée(s) · {{ $component['failure'] }} échec(s) · {{ $component['reverted'] }} restauration(s) · {{ $component['unconfirmed'] }} sans confirmation</div>
            <div class="ops-muted">{{ $component['version_type'] }} : {{ $component['planned'] ? 'V'.$component['planned'] : 'constatée par Centrex' }}</div>
            <a href="{{ route('tools.centrex_hr.updates', ['module' => $component['key']]) }}">Voir les Centrex et les dates →</a>
          </article>
        @endforeach
        </div>
      @endif
    </section>
  @else
    <section class="ops-panel"><h2>Aucun bilan importé</h2><p>Les chiffres apparaîtront ici après l'import d'un rapport de campagne. L'inventaire OVH reste disponible séparément.</p></section>
  @endif

  @if($recentCampaigns->isNotEmpty())
    <section class="ops-panel"><h2>Campagnes enregistrées</h2>
      <div class="ops-history">
      @foreach($recentCampaigns as $campaign)
        <a href="{{ route('tools.centrex_hr.campaign', $campaign) }}">
          <strong>{{ $campaign->operation }} · {{ $campaign->created_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}</strong><br>
          <span class="ops-muted">{{ $campaign->processed }} traités · {{ $campaign->failed }} échecs</span>
        </a>
      @endforeach
      </div>
    </section>
  @endif

  <footer class="ops-footer">Centrex-HR · Historique des interventions, sans mesure des appels</footer>
</div></div>
@endsection
