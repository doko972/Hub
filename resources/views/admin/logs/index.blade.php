@extends('layouts.app')

@section('title', 'Journaux d\'activité')
@section('page-title', 'Journaux')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Journaux d'activité</h1>
        <p class="page-header__subtitle">{{ $logs->total() }} entrée(s) enregistrée(s)</p>
    </div>
</div>

@if($logs->isEmpty())
    <div class="card">
        <div class="card__body" style="text-align:center; padding:60px;">
            <p style="color:#6B7280;">Aucune activité enregistrée pour le moment.</p>
        </div>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Sujet</th>
                    <th>Détail</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td style="white-space:nowrap; font-size:12px; color:#9CA3AF;">
                        <span title="{{ $log->created_at->format('d/m/Y H:i:s') }}">
                            {{ $log->created_at->diffForHumans() }}
                        </span>
                    </td>
                    <td>
                        @if($log->user)
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:28px; height:28px; border-radius:50%; background:#7C3AED; color:white;
                                            display:flex; align-items:center; justify-content:center;
                                            font-size:10px; font-weight:700; flex-shrink:0;">
                                    {{ $log->user->initials() }}
                                </div>
                                <span style="font-size:13px;">{{ $log->user->name }}</span>
                            </div>
                        @else
                            <span style="color:#D1D5DB; font-size:12px;">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $badgeClass = match($log->action) {
                                'created'  => 'badge--active',
                                'updated'  => 'badge--user',
                                'deleted'  => 'badge--danger',
                                'assigned' => 'badge--admin',
                                default    => 'badge--inactive',
                            };
                            $label = match($log->action) {
                                'created'  => 'Créé',
                                'updated'  => 'Modifié',
                                'deleted'  => 'Supprimé',
                                'assigned' => 'Assigné',
                                default    => $log->action,
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                    </td>
                    <td style="font-size:13px; color:#6B7280;">{{ $log->subject_type }}</td>
                    <td style="font-size:13px; max-width:320px;">
                        @if($log->subject_name)
                            {{ $log->subject_name }}
                        @else
                            <span style="color:#D1D5DB;">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>
@endif

@endsection
