@extends('layouts.app')

@section('title', 'Gestion des outils')
@section('page-title', 'Outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Outils</h1>
        <p class="page-header__subtitle">{{ $tools->total() }} outil(s) enregistré(s)</p>
    </div>
    <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nouvel outil
    </a>
</div>

@if($tools->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding: 60px;">
            <p style="color: #6B7280; margin-bottom: 16px;">Aucun outil créé pour l'instant.</p>
            <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">Créer le premier outil</a>
        </div>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th style="width:48px;">#</th>
                    <th style="width:52px;">Image</th>
                    <th>Titre</th>
                    <th>Famille</th>
                    <th>URL</th>
                    <th>Visibilité</th>
                    <th>Statut</th>
                    <th>Ordre</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody
                data-sortable
                data-sortable-url="{{ route('admin.tools.reorder') }}"
                data-page="{{ $tools->currentPage() }}"
                data-per-page="{{ $tools->perPage() }}">
                @foreach($tools as $tool)
                <tr data-id="{{ $tool->id }}">
                    <td style="padding:0 8px;">
                        <span class="drag-handle" title="Déplacer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9"  cy="5"  r="1" fill="currentColor"/><circle cx="15" cy="5"  r="1" fill="currentColor"/>
                                <circle cx="9"  cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/>
                                <circle cx="9"  cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>
                            </svg>
                        </span>
                    </td>
                    <td style="color:#9CA3AF; font-size:12px;">{{ $tool->id }}</td>
                    <td>
                        <div class="table-thumb">
                            @if($tool->imageUrl())
                                <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->title }}">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                            @endif
                        </div>
                    </td>
                    <td>
                        <strong>{{ $tool->title }}</strong>
                        @if($tool->description)
                            <br><span style="font-size:12px; color:#9CA3AF;">{{ Str::limit($tool->description, 60) }}</span>
                        @endif
                    </td>
                    <td>
                        @if($tool->family)
                            <span class="badge badge--{{ $tool->family->color }}">{{ $tool->family->name }}</span>
                        @else
                            <span style="color:#D1D5DB; font-size:12px;">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ $tool->url }}" target="_blank" rel="noopener"
                           style="color:#7C3AED; font-size:12px; word-break:break-all;">
                            {{ Str::limit($tool->url, 40) }}
                        </a>
                    </td>
                    <td>
                        @if($tool->is_public)
                            <span class="badge badge--active">Public</span>
                        @else
                            <span class="badge badge--user">Assigné</span>
                        @endif
                    </td>
                    <td>
                        @if($tool->is_active)
                            <span class="badge badge--active">Actif</span>
                        @else
                            <span class="badge badge--inactive">Inactif</span>
                        @endif
                    </td>
                    <td style="text-align:center;">{{ $tool->sort_order }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.tools.edit', $tool) }}" class="btn btn--secondary btn--sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Modifier
                            </a>
                            <form method="POST" action="{{ route('admin.tools.destroy', $tool) }}"
                                  data-confirm="Supprimer l'outil « {{ $tool->title }} » ? Cette action est irréversible.">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn--danger btn--sm">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                        <path d="M10 11v6"/><path d="M14 11v6"/>
                                        <path d="M9 6V4h6v2"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $tools->links() }}
    </div>
@endif

@endsection
