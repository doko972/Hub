@extends('layouts.app')

@section('title', 'Assignation en masse')
@section('page-title', 'Assignation')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Assignation en masse</h1>
        <p class="page-header__subtitle">Assignez ou retirez plusieurs outils à plusieurs utilisateurs en une seule opération.</p>
    </div>
</div>

@if($tools->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding:60px;">
            <p style="color:#6B7280; margin-bottom:16px;">Aucun outil assignable.<br>Les outils publics sont accessibles à tous et ne nécessitent pas d'assignation.</p>
            <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">Créer un outil</a>
        </div>
    </div>

@elseif($users->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding:60px;">
            <p style="color:#6B7280;">Aucun utilisateur actif à gérer.</p>
        </div>
    </div>

@else

<form method="POST" action="{{ route('admin.assignments.update') }}">
    @csrf

    <div class="assign-layout">

        {{-- ---- Colonne Outils ---- --}}
        <div class="card">
            <div class="card__header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="card__title">Outils ({{ $tools->count() }})</h2>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn--secondary btn--sm" data-check-all="tools">Tout cocher</button>
                    <button type="button" class="btn btn--secondary btn--sm" data-uncheck-all="tools">Tout décocher</button>
                </div>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="assign-list">
                    @foreach($tools as $tool)
                        <label class="assign-item" data-group="tools">
                            <input type="checkbox" name="tools[]" value="{{ $tool->id }}" class="assign-item__checkbox">
                            <div class="assign-item__thumb tile--{{ $tool->color }}">
                                @if($tool->imageUrl())
                                    <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->title }}">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="assign-item__info">
                                <span class="assign-item__name">{{ $tool->title }}</span>
                                @if($tool->family)
                                    <span class="assign-item__meta">{{ $tool->family->name }}</span>
                                @endif
                            </div>
                            <span class="assign-item__check">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ---- Colonne Utilisateurs ---- --}}
        <div class="card">
            <div class="card__header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="card__title">Utilisateurs ({{ $users->count() }})</h2>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn--secondary btn--sm" data-check-all="users">Tout cocher</button>
                    <button type="button" class="btn btn--secondary btn--sm" data-uncheck-all="users">Tout décocher</button>
                </div>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="assign-list">
                    @foreach($users as $user)
                        <label class="assign-item" data-group="users">
                            <input type="checkbox" name="users[]" value="{{ $user->id }}" class="assign-item__checkbox">
                            <div class="assign-item__avatar">
                                @if($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                                @else
                                    {{ $user->initials() }}
                                @endif
                            </div>
                            <div class="assign-item__info">
                                <span class="assign-item__name">{{ $user->name }}</span>
                                <span class="assign-item__meta">{{ $user->tools_count }} outil(s) assigné(s)</span>
                            </div>
                            <span class="assign-item__check">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- ---- Action + Soumettre ---- --}}
    <div class="card" style="margin-top: 24px;">
        <div class="card__body" style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
            <div style="display:flex; gap:16px; align-items:center;">
                <span style="font-size:14px; font-weight:600; color:var(--text, #1F2937);">Action :</span>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px;">
                    <input type="radio" name="action" value="assign" checked> Assigner
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px;">
                    <input type="radio" name="action" value="remove"> Retirer
                </label>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-left:auto;">
                Appliquer la sélection
            </button>
        </div>
    </div>

</form>

@push('scripts')
<script>
    // Tout cocher / décocher par groupe
    document.querySelectorAll('[data-check-all]').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.checkAll;
            document.querySelectorAll(`[data-group="${group}"] .assign-item__checkbox`).forEach(cb => {
                cb.checked = true;
                cb.closest('.assign-item').classList.add('is-checked');
            });
        });
    });
    document.querySelectorAll('[data-uncheck-all]').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.uncheckAll;
            document.querySelectorAll(`[data-group="${group}"] .assign-item__checkbox`).forEach(cb => {
                cb.checked = false;
                cb.closest('.assign-item').classList.remove('is-checked');
            });
        });
    });
    // Toggle visuel
    document.querySelectorAll('.assign-item__checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.assign-item').classList.toggle('is-checked', cb.checked);
        });
    });
</script>
@endpush

@endif

@endsection
