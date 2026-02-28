@extends('layouts.app')

@section('title', 'Gestion des utilisateurs')
@section('page-title', 'Utilisateurs')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Utilisateurs</h1>
        <p class="page-header__subtitle">{{ $users->total() }} utilisateur(s) enregistré(s)</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nouvel utilisateur
    </a>
</div>

@if($users->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding: 60px;">
            <p style="color: #6B7280;">Aucun utilisateur trouvé.</p>
        </div>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:48px;">#</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Outils assignés</th>
                    <th>Créé le</th>
                    <th style="width:140px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td style="color:#9CA3AF; font-size:12px;">{{ $user->id }}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:50%; background:#7C3AED; color:white;
                                        display:flex; align-items:center; justify-content:center;
                                        font-size:12px; font-weight:700; flex-shrink:0;">
                                {{ $user->initials() }}
                            </div>
                            <strong>{{ $user->name }}</strong>
                            @if($user->id === auth()->id())
                                <span style="font-size:11px; color:#7C3AED; font-weight:600;">(vous)</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if($user->isAdmin())
                            <span class="badge badge--admin">Administrateur</span>
                        @else
                            <span class="badge badge--user">Utilisateur</span>
                        @endif
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge--active">Actif</span>
                        @else
                            <span class="badge badge--inactive">Inactif</span>
                        @endif
                    </td>
                    <td>
                        @if($user->isAdmin())
                            <span style="color:#9CA3AF; font-size:12px;">Tous (admin)</span>
                        @else
                            <span style="font-size:13px;">{{ $user->tools->count() }} outil(s)</span>
                        @endif
                    </td>
                    <td style="font-size:12px; color:#9CA3AF;">
                        {{ $user->created_at->format('d/m/Y') }}
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn--secondary btn--sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Modifier
                            </a>
                            @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                      data-confirm="Supprimer l'utilisateur « {{ $user->name }} » ? Cette action est irréversible.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                                            <path d="M10 11v6"/><path d="M14 11v6"/>
                                            <path d="M9 6V4h6v2"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $users->links() }}
    </div>
@endif

@endsection
