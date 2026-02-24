@extends('layouts.app')

@section('title', 'Modifier ' . $tool->title)
@section('page-title', 'Outils › Modifier')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Modifier : {{ $tool->title }}</h1>
        <p class="page-header__subtitle">Modifiez les informations de cet outil.</p>
    </div>
    <div class="btn-group">
        <a href="{{ $tool->url }}" target="_blank" rel="noopener" class="btn btn--secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            Tester
        </a>
        <a href="{{ route('admin.tools.index') }}" class="btn btn--secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Retour
        </a>
    </div>
</div>

<form method="POST"
      action="{{ route('admin.tools.update', $tool) }}"
      enctype="multipart/form-data"
      novalidate>
    @csrf @method('PUT')
    @include('admin.tools._form')
</form>

@endsection
