@extends('layouts.app')

@section('title', 'Créer un outil')
@section('page-title', 'Outils › Créer')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Nouvel outil</h1>
        <p class="page-header__subtitle">Ajoutez un outil à votre dashboard.</p>
    </div>
    <a href="{{ route('admin.tools.index') }}" class="btn btn--secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
</div>

<form method="POST"
      action="{{ route('admin.tools.store') }}"
      enctype="multipart/form-data"
      novalidate>
    @csrf
    @include('admin.tools._form', ['tool' => null, 'assigned' => []])
</form>

@endsection
