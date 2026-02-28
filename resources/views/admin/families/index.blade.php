@extends('layouts.app')

@section('title', 'Familles d\'outils')
@section('page-title', 'Familles')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Familles d'outils</h1>
        <p class="page-header__subtitle">{{ $families->total() }} famille(s) enregistrée(s)</p>
    </div>
    <a href="{{ route('admin.families.create') }}" class="btn btn--primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nouvelle famille
    </a>
</div>

@if($families->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding: 60px;">
            <p style="color: #6B7280; margin-bottom: 16px;">Aucune famille créée pour l'instant.</p>
            <a href="{{ route('admin.families.create') }}" class="btn btn--primary">Créer la première famille</a>
        </div>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th style="width:48px;">#</th>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Couleur</th>
                    <th>Outils</th>
                    <th>Statut</th>
                    <th>Ordre</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody
                data-sortable
                data-sortable-url="{{ route('admin.families.reorder') }}"
                data-page="{{ $families->currentPage() }}"
                data-per-page="{{ $families->perPage() }}">
                @foreach($families as $family)
                <tr data-id="{{ $family->id }}">
                    <td style="padding:0 8px;">
                        <span class="drag-handle" title="Déplacer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9"  cy="5"  r="1" fill="currentColor"/><circle cx="15" cy="5"  r="1" fill="currentColor"/>
                                <circle cx="9"  cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/>
                                <circle cx="9"  cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>
                            </svg>
                        </span>
                    </td>
                    <td style="color:#9CA3AF; font-size:12px;">{{ $family->id }}</td>
                    <td><strong>{{ $family->name }}</strong></td>
                    <td>
                        @if($family->description)
                            <span style="font-size:12px; color:#9CA3AF;">{{ Str::limit($family->description, 60) }}</span>
                        @else
                            <span style="color:#D1D5DB;">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge--{{ $family->color }}" style="text-transform:capitalize;">
                            {{ $family->color }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge badge--active">{{ $family->tools_count }}</span>
                    </td>
                    <td>
                        @if($family->is_active)
                            <span class="badge badge--active">Active</span>
                        @else
                            <span class="badge badge--inactive">Inactive</span>
                        @endif
                    </td>
                    <td style="text-align:center;">{{ $family->sort_order }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.families.edit', $family) }}" class="btn btn--secondary btn--sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Modifier
                            </a>
                            <form method="POST" action="{{ route('admin.families.destroy', $family) }}"
                                  data-confirm="Supprimer la famille « {{ $family->name }} » ? Les outils associés n'auront plus de famille.">
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
        {{ $families->links() }}
    </div>
@endif

@endsection
