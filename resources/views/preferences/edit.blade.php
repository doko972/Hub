@extends('layouts.app')

@section('title', 'Mes outils')
@section('page-title', 'Mes outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Mes outils</h1>
        <p class="page-header__subtitle">Cochez les outils que vous souhaitez afficher sur votre dashboard.</p>
    </div>
</div>

@if($families->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding:60px;">
            <p style="color:#6B7280;">Aucun outil ne vous est accessible pour le moment.<br>Contactez un administrateur.</p>
        </div>
    </div>
@else

<form method="POST" action="{{ route('preferences.update') }}">
    @csrf

    {{-- Boutons de contrôle rapide + Enregistrer --}}
    <div class="pref-toolbar">
        <div class="pref-toolbar__controls">
            <button type="button" class="btn btn--secondary btn--sm" id="check-all">Tout cocher</button>
            <button type="button" class="btn btn--secondary btn--sm" id="uncheck-all">Tout décocher</button>
        </div>
        <button type="submit" class="btn btn--primary">Enregistrer</button>
    </div>

    {{-- Liste par famille --}}
    @foreach($families as $family)
        <div class="tool-group">
            <h2 class="tool-group__title">{{ $family->name }}</h2>

            <div class="pref-grid">
                @foreach($family->tools as $tool)
                    @php
                        $checked = $allCheckedByDefault || in_array($tool->id, $selectedIds);
                    @endphp
                    <label class="pref-item {{ $checked ? 'is-checked' : '' }}">
                        <input type="checkbox"
                               name="tools[]"
                               value="{{ $tool->id }}"
                               class="pref-item__checkbox"
                               {{ $checked ? 'checked' : '' }}>

                        {{-- Miniature --}}
                        <div class="pref-item__thumb tile--{{ $tool->color }}">
                            @if($tool->imageUrl())
                                <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->title }}">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Infos --}}
                        <div class="pref-item__info">
                            <span class="pref-item__title">{{ $tool->title }}</span>
                            @if($tool->description)
                                <span class="pref-item__desc">{{ $tool->description }}</span>
                            @endif
                        </div>

                        {{-- Checkmark visuel --}}
                        <div class="pref-item__check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Bouton Enregistrer en bas --}}
    <div style="display:flex; justify-content:flex-end; margin-top: 8px;">
        <button type="submit" class="btn btn--primary">Enregistrer</button>
    </div>

</form>

<script>
    // Tout cocher / décocher
    document.getElementById('check-all')?.addEventListener('click', () => {
        document.querySelectorAll('.pref-item__checkbox').forEach(cb => {
            cb.checked = true;
            cb.closest('.pref-item').classList.add('is-checked');
        });
    });
    document.getElementById('uncheck-all')?.addEventListener('click', () => {
        document.querySelectorAll('.pref-item__checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.pref-item').classList.remove('is-checked');
        });
    });
    // Mise à jour visuelle au clic sur chaque item
    document.querySelectorAll('.pref-item__checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('.pref-item').classList.toggle('is-checked', cb.checked);
        });
    });
</script>

@endif

@endsection
