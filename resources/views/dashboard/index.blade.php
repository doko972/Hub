@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Mes outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Bonjour, {{ auth()->user()->name }}</h1>
        <p class="page-header__subtitle">Accédez rapidement à vos outils de travail.</p>
    </div>

    @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Ajouter un outil
        </a>
    @endif
</div>

@if($families->isEmpty())
    <div style="text-align:center; padding: 80px 20px; color: var(--color-text-muted, #6B7280);">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 16px; opacity: 0.4;">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        <p style="font-size:18px; font-weight:600; margin-bottom:8px;">Aucun outil disponible</p>
        @if(auth()->user()->isAdmin())
            <p style="margin-bottom:24px;">Commencez par créer une famille, puis ajoutez vos outils.</p>
            <a href="{{ route('admin.families.create') }}" class="btn btn--primary">Créer une famille</a>
        @else
            <p>Aucun outil ne vous a encore été assigné. Contactez un administrateur.</p>
        @endif
    </div>

@else

    {{-- Barre de recherche rapide --}}
    <div class="dashboard-search">
        <div class="dashboard-search__wrap">
            <svg class="dashboard-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
                type="search"
                id="tool-search"
                class="dashboard-search__input"
                placeholder="Rechercher un outil…"
                autocomplete="off"
            >
        </div>
    </div>

    @foreach($families as $family)
        <div class="tool-group">
            <h2 class="tool-group__title">{{ $family->name }}</h2>

            <div class="tiles-grid">

                @foreach($family->tools as $tool)
                    <a href="{{ $tool->url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="tile tile--{{ $tool->color }}"
                       title="{{ $tool->title }}">

                        {{-- Image ou icône --}}
                        <div class="tile__image-wrap">
                            @if($tool->imageUrl())
                                <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->title }}" class="tile__image">
                            @else
                                <svg class="tile__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Titre --}}
                        <span class="tile__title">{{ $tool->title }}</span>

                        {{-- Tooltip (description au survol) --}}
                        @if($tool->description)
                            <span class="tile__tooltip" role="tooltip">{{ $tool->description }}</span>
                        @endif

                    </a>
                @endforeach

                {{-- Bouton ajout (admin uniquement, dans la dernière famille) --}}
                @if(auth()->user()->isAdmin() && $loop->last)
                    <a href="{{ route('admin.tools.create') }}" class="tile tile--add">
                        <svg class="tile__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        <span class="tile__title">Ajouter</span>
                    </a>
                @endif

            </div>
        </div>
    @endforeach

    {{-- Message aucun résultat --}}
    <div id="search-empty" style="display:none; text-align:center; padding:48px 20px; color:var(--color-text-muted, #6B7280);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 12px; opacity: 0.4;">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <p style="font-size:16px; font-weight:600; margin-bottom:6px;">Aucun résultat</p>
        <p style="font-size:14px;">Aucun outil ne correspond à votre recherche.</p>
    </div>

@endif

@push('scripts')
<script>
    const searchInput = document.getElementById('tool-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let anyVisible = false;

            document.querySelectorAll('.tool-group').forEach(group => {
                const tiles = group.querySelectorAll('.tile:not(.tile--add)');
                let groupVisible = 0;

                tiles.forEach(tile => {
                    const title = (tile.querySelector('.tile__title')?.textContent || '').toLowerCase();
                    const match = !q || title.includes(q);
                    tile.style.display = match ? '' : 'none';
                    if (match) groupVisible++;
                });

                group.style.display = groupVisible > 0 || !q ? '' : 'none';
                if (groupVisible > 0) anyVisible = true;
            });

            const empty = document.getElementById('search-empty');
            if (empty) empty.style.display = !anyVisible && q ? '' : 'none';
        });
    }
</script>
@endpush

@endsection
