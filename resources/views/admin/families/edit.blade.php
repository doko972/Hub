@extends('layouts.app')

@section('title', 'Modifier la famille')
@section('page-title', 'Familles')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Modifier « {{ $family->name }} »</h1>
        <p class="page-header__subtitle">{{ $family->tools()->count() }} outil(s) dans cette famille.</p>
    </div>
    <a href="{{ route('admin.families.index') }}" class="btn btn--secondary">Retour</a>
</div>

<form method="POST" action="{{ route('admin.families.update', $family) }}">
    @csrf @method('PUT')
    @include('admin.families._form')
</form>

@endsection
