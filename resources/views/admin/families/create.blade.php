@extends('layouts.app')

@section('title', 'Nouvelle famille')
@section('page-title', 'Familles')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Nouvelle famille</h1>
        <p class="page-header__subtitle">Créez un groupe pour organiser vos outils.</p>
    </div>
    <a href="{{ route('admin.families.index') }}" class="btn btn--secondary">Retour</a>
</div>

<form method="POST" action="{{ route('admin.families.store') }}">
    @csrf
    @include('admin.families._form')
</form>

@endsection
