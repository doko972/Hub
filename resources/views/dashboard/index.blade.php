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

@if($tools->isEmpty())
    <div style="text-align:center; padding: 80px 20px; color: var(--color-text-muted, #6B7280);">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 16px; opacity: 0.4;">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        <p style="font-size:18px; font-weight:600; margin-bottom:8px;">Aucun outil disponible</p>
        @if(auth()->user()->isAdmin())
            <p style="margin-bottom:24px;">Commencez par ajouter votre premier outil.</p>
            <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">Ajouter un outil</a>
        @else
            <p>Aucun outil ne vous a encore été assigné. Contactez un administrateur.</p>
        @endif
    </div>

@else

    <div class="tiles-grid">

        @foreach($tools as $tool)
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

        {{-- Bouton ajout (admin uniquement) --}}
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.tools.create') }}" class="tile tile--add">
                <svg class="tile__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span class="tile__title">Ajouter</span>
            </a>
        @endif

    </div>

@endif

@endsection
