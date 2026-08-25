@extends('layouts.app')

@section('title', 'Centrex FreePBX')
@section('page-title', 'Outils')

@section('content')

@php
    // Le formulaire se rouvre tout seul après une erreur de validation :
    // sinon l'utilisateur perd sa saisie en même temps que la modale.
    $reouvrir = $errors->any();
    $editionId = old('pbx_id');
@endphp

<div class="page-header">
    <div>
        <h1 class="page-header__title">Centrex FreePBX</h1>
        <p class="page-header__subtitle">
            {{ $servers->total() }} centrex référencé(s) — accès aux interfaces d'administration
        </p>
    </div>
    <button type="button" class="btn btn--primary" data-pbx-new>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nouveau centrex
    </button>
</div>

{{-- Recherche --}}
<form method="GET" action="{{ route('tools.centrex.index') }}" class="pbx-search">
    <div class="pbx-search__field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="search" name="q" value="{{ $q }}" class="form-control"
               placeholder="Rechercher un centrex, un client, une IP…" autocomplete="off">
    </div>
    <button type="submit" class="btn btn--secondary">Rechercher</button>
    @if($q !== '')
        <a href="{{ route('tools.centrex.index') }}" class="btn btn--ghost">Effacer</a>
    @endif
</form>

@if($servers->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding:60px;">
            @if($q !== '')
                <p style="color:var(--text-muted); margin-bottom:16px;">
                    Aucun centrex ne correspond à « {{ $q }} ».
                </p>
                <a href="{{ route('tools.centrex.index') }}" class="btn btn--secondary">Voir tous les centrex</a>
            @else
                <p style="color:var(--text-muted); margin-bottom:16px;">
                    Aucun centrex référencé pour l'instant.
                </p>
                <button type="button" class="btn btn--primary" data-pbx-new>Ajouter le premier centrex</button>
            @endif
        </div>
    </div>
@else
    <div class="pbx-grid">
        @foreach($servers as $centrex)
            @php
                // Tout sauf le mot de passe : il ne transite que par la route
                // « secret », à l'ouverture explicite de la fiche.
                $fiche = [
                    'id'          => $centrex->id,
                    'name'        => $centrex->name,
                    'client'      => $centrex->client,
                    'protocol'    => $centrex->protocol,
                    'host'        => $centrex->host,
                    'port'        => $centrex->port,
                    'path'        => $centrex->path,
                    'login'       => $centrex->login,
                    'notes'       => $centrex->notes,
                    'is_active'   => $centrex->is_active,
                    'hasPassword' => filled($centrex->password),
                    'updateUrl'   => route('tools.centrex.update', $centrex),
                ];
            @endphp

            <article class="pbx-card {{ $centrex->is_active ? '' : 'pbx-card--off' }}"
                     data-pbx="@json($fiche)">

                <header class="pbx-card__head">
                    <div class="pbx-card__identity">
                        <h2 class="pbx-card__name">{{ $centrex->name }}</h2>
                        @if($centrex->client)
                            <span class="pbx-card__client">{{ $centrex->client }}</span>
                        @endif
                    </div>
                    <span class="pbx-card__state {{ $centrex->is_active ? 'is-on' : 'is-off' }}"
                          title="{{ $centrex->is_active ? 'Actif' : 'Hors service' }}">
                        {{ $centrex->is_active ? 'Actif' : 'Inactif' }}
                    </span>
                </header>

                <div class="pbx-card__host">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <rect x="2" y="3"  width="20" height="8" rx="2"/>
                        <rect x="2" y="13" width="20" height="8" rx="2"/>
                        <line x1="6" y1="7"  x2="6.01" y2="7"/>
                        <line x1="6" y1="17" x2="6.01" y2="17"/>
                    </svg>
                    <code>{{ $centrex->hostLabel() }}</code>
                    <button type="button" class="pbx-card__copy"
                            data-pbx-copy="{{ $centrex->hostLabel() }}"
                            title="Copier l'adresse" aria-label="Copier l'adresse">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>

                @if($centrex->notes)
                    <p class="pbx-card__notes">{{ $centrex->notes }}</p>
                @endif

                <footer class="pbx-card__actions">
                    <a href="{{ $centrex->url() }}" target="_blank" rel="noopener noreferrer"
                       class="btn btn--primary btn--sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        Ouvrir
                    </a>

                    <button type="button" class="btn btn--secondary btn--sm" data-pbx-secret
                            data-url="{{ route('tools.centrex.secret', $centrex) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3"/>
                        </svg>
                        Identifiants
                    </button>

                    <button type="button" class="btn btn--ghost btn--sm" data-pbx-edit>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Modifier
                    </button>

                    <form method="POST" action="{{ route('tools.centrex.destroy', $centrex) }}"
                          data-confirm="Supprimer le centrex « {{ $centrex->name }} » ? Les identifiants enregistrés seront perdus.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--sm" title="Supprimer" aria-label="Supprimer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                            </svg>
                        </button>
                    </form>
                </footer>
            </article>
        @endforeach
    </div>

    {{ $servers->links() }}
@endif


{{-- ===================== MODALE : AJOUT / MODIFICATION ===================== --}}
<div class="pbx-overlay {{ $reouvrir ? 'is-open' : '' }}" id="pbx-form-overlay" aria-hidden="{{ $reouvrir ? 'false' : 'true' }}">
    <div class="pbx-modal" role="dialog" aria-modal="true" aria-labelledby="pbx-form-title">

        {{-- data-update-url : gabarit d'URL d'édition. Il permet de rouvrir la
             bonne fiche même quand la validation échoue sur une carte qui n'est
             pas dans la page courante de la pagination. --}}
        <form method="POST" action="{{ route('tools.centrex.store') }}" id="pbx-form"
              data-store-url="{{ route('tools.centrex.store') }}"
              data-update-url="{{ route('tools.centrex.update', ['centrex' => '__ID__']) }}">
            @csrf
            {{-- Rempli par le JS en mode édition : sert aussi à rouvrir la bonne
                 fiche quand la validation échoue. --}}
            <input type="hidden" name="pbx_id" id="pbx_id" value="{{ old('pbx_id') }}">
            <input type="hidden" name="_method" id="pbx-method" value="POST" disabled>

            <div class="pbx-modal__header">
                <h2 class="pbx-modal__title" id="pbx-form-title">Nouveau centrex</h2>
                <button type="button" class="pbx-modal__close" data-pbx-close aria-label="Fermer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="pbx-modal__body">

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label" for="pbx-name">Nom <span class="required">*</span></label>
                        <input type="text" id="pbx-name" name="name" required maxlength="80"
                               class="form-control {{ $errors->has('name') ? 'form-control--error' : '' }}"
                               value="{{ old('name') }}" placeholder="Ex : Centrex Martin SAS">
                        @error('name')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pbx-client">Client</label>
                        <input type="text" id="pbx-client" name="client" maxlength="120"
                               class="form-control {{ $errors->has('client') ? 'form-control--error' : '' }}"
                               value="{{ old('client') }}" placeholder="Société ou site">
                        @error('client')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row form-row--3">
                    <div class="form-group">
                        <label class="form-label" for="pbx-protocol">Protocole</label>
                        <select id="pbx-protocol" name="protocol" class="form-control">
                            @foreach($protocols as $value => $label)
                                <option value="{{ $value }}" {{ old('protocol', 'https') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pbx-host">Adresse IP / hôte <span class="required">*</span></label>
                        <input type="text" id="pbx-host" name="host" required maxlength="190"
                               class="form-control {{ $errors->has('host') ? 'form-control--error' : '' }}"
                               value="{{ old('host') }}" placeholder="192.168.1.50">
                        @error('host')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pbx-port">Port</label>
                        <input type="number" id="pbx-port" name="port" min="1" max="65535"
                               class="form-control {{ $errors->has('port') ? 'form-control--error' : '' }}"
                               value="{{ old('port') }}" placeholder="443">
                        @error('port')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pbx-path">Chemin d'administration</label>
                    <input type="text" id="pbx-path" name="path" maxlength="120"
                           class="form-control {{ $errors->has('path') ? 'form-control--error' : '' }}"
                           value="{{ old('path', '/admin') }}" placeholder="/admin">
                    <span class="form-hint">
                        Page ouverte par le bouton « Ouvrir ». <code>/admin</code> pour l'interface FreePBX standard,
                        <code>/ucp</code> pour le portail utilisateur.
                    </span>
                    @error('path')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row form-row--2">
                    <div class="form-group">
                        <label class="form-label" for="pbx-login">Identifiant</label>
                        <input type="text" id="pbx-login" name="login" maxlength="190" autocomplete="off"
                               class="form-control {{ $errors->has('login') ? 'form-control--error' : '' }}"
                               value="{{ old('login') }}" placeholder="admin">
                        @error('login')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pbx-password">Mot de passe</label>
                        <div class="input-password">
                            <input type="password" id="pbx-password" name="password" maxlength="255"
                                   autocomplete="new-password"
                                   class="form-control {{ $errors->has('password') ? 'form-control--error' : '' }}"
                                   placeholder="••••••••">
                            <button type="button" class="input-password__toggle" aria-label="Afficher le mot de passe">
                                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="icon-eye-off hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                        <span class="form-hint" id="pbx-password-hint" hidden>
                            Laissez vide pour conserver le mot de passe actuel.
                        </span>
                        <label class="pbx-clear" id="pbx-clear-wrap" hidden>
                            <input type="checkbox" name="clear_password" value="1">
                            <span>Effacer le mot de passe enregistré</span>
                        </label>
                        @error('password')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pbx-notes">Notes</label>
                    <textarea id="pbx-notes" name="notes" rows="3" maxlength="2000"
                              class="form-control {{ $errors->has('notes') ? 'form-control--error' : '' }}"
                              placeholder="Version FreePBX, VPN à monter, trunk utilisé…">{{ old('notes') }}</textarea>
                    @error('notes')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    {{-- Le champ caché fait exister « is_active » même décoché :
                         sans lui, old() ne saurait pas distinguer « désactivé »
                         de « absent » au retour d'une erreur de validation. --}}
                    <input type="hidden" name="is_active" value="0">
                    <label class="form-toggle">
                        <input type="checkbox" name="is_active" value="1" id="pbx-active"
                               {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Centrex en service</span>
                    </label>
                </div>

            </div>

            <div class="pbx-modal__footer">
                <button type="button" class="btn btn--secondary" data-pbx-close>Annuler</button>
                <button type="submit" class="btn btn--primary" id="pbx-submit">Ajouter le centrex</button>
            </div>
        </form>
    </div>
</div>


{{-- ===================== MODALE : IDENTIFIANTS ===================== --}}
<div class="pbx-overlay" id="pbx-secret-overlay" aria-hidden="true">
    <div class="pbx-modal pbx-modal--narrow" role="dialog" aria-modal="true" aria-labelledby="pbx-secret-title">

        <div class="pbx-modal__header">
            <h2 class="pbx-modal__title" id="pbx-secret-title">Identifiants</h2>
            <button type="button" class="pbx-modal__close" data-pbx-close aria-label="Fermer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="pbx-modal__body">
            <p class="pbx-secret__host" id="pbx-secret-host"></p>

            <div class="form-group">
                <label class="form-label" for="pbx-secret-login">Identifiant</label>
                <div class="pbx-secret__field">
                    <input type="text" id="pbx-secret-login" class="form-control" readonly>
                    <button type="button" class="pbx-secret__btn" data-pbx-copy-field="pbx-secret-login"
                            title="Copier" aria-label="Copier l'identifiant">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="pbx-secret-password">Mot de passe</label>
                <div class="pbx-secret__field">
                    <input type="password" id="pbx-secret-password" class="form-control" readonly>
                    <button type="button" class="pbx-secret__btn" id="pbx-secret-eye"
                            title="Afficher" aria-label="Afficher le mot de passe">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                    <button type="button" class="pbx-secret__btn" data-pbx-copy-field="pbx-secret-password"
                            title="Copier" aria-label="Copier le mot de passe">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="pbx-modal__footer">
            <button type="button" class="btn btn--secondary" data-pbx-close>Fermer</button>
            <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn--primary" id="pbx-secret-open">
                Ouvrir l'interface
            </a>
        </div>
    </div>
</div>

@endsection
