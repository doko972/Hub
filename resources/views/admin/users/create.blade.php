@extends('layouts.app')

@section('title', 'Créer un utilisateur')
@section('page-title', 'Utilisateurs › Créer')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Nouvel utilisateur</h1>
        <p class="page-header__subtitle">Créez un compte et définissez ses accès.</p>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn--secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</div>

<form method="POST" action="{{ route('admin.users.store') }}" novalidate>
    @csrf
    @include('admin.users._form', ['user' => null, 'assigned' => []])
</form>

@endsection
