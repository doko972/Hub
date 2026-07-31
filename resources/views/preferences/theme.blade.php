@extends('layouts.app')

@section('title', 'Thème')
@section('page-title', 'Thème')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Thème</h1>
        <p class="page-header__subtitle">
            Choisissez l'apparence du Hub. Le changement est immédiat et vous suit sur tous vos appareils.
        </p>
    </div>
</div>

<div class="theme-grid">
    @foreach($themes as $key => $theme)
        <button type="button"
                class="theme-card {{ $key === $current ? 'is-active' : '' }}"
                data-theme-choice="{{ $key }}"
                aria-pressed="{{ $key === $current ? 'true' : 'false' }}">

            {{-- Aperçu : miniature d'interface aux couleurs du thème.
                 Les couleurs viennent de config/themes.php car un aperçu doit
                 montrer un thème que la page n'applique pas. --}}
            <span class="theme-card__preview" aria-hidden="true"
                  style="background: {{ $theme['swatch'][0] }}">
                <span class="theme-card__preview-bar" style="background: {{ $theme['swatch'][2] }}"></span>
                <span class="theme-card__preview-body">
                    <span class="theme-card__preview-card" style="background: {{ $theme['swatch'][1] }}">
                        <span style="background: {{ $theme['swatch'][2] }}"></span>
                        <span style="background: {{ $theme['swatch'][2] }}; opacity: .45"></span>
                    </span>
                    <span class="theme-card__preview-card" style="background: {{ $theme['swatch'][1] }}">
                        <span style="background: {{ $theme['swatch'][2] }}; opacity: .7"></span>
                        <span style="background: {{ $theme['swatch'][2] }}; opacity: .3"></span>
                    </span>
                </span>
            </span>

            <span class="theme-card__info">
                <span class="theme-card__name">{{ $theme['label'] }}</span>
                <span class="theme-card__desc">{{ $theme['desc'] }}</span>
            </span>

            <span class="theme-card__check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </span>
        </button>
    @endforeach
</div>

{{-- Repli sans JavaScript : le clic sur une carte est géré par theme.js,
     ce formulaire garantit que le choix reste possible sans JS. --}}
<noscript>
    <form method="POST" action="{{ route('preferences.theme.update') }}" class="card" style="margin-top:16px;">
        @csrf
        <div class="card__body">
            <label class="form-label" for="theme-select">Thème</label>
            <select name="theme" id="theme-select" class="form-control">
                @foreach($themes as $key => $theme)
                    <option value="{{ $key }}" {{ $key === $current ? 'selected' : '' }}>{{ $theme['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Appliquer</button>
        </div>
    </form>
</noscript>

@endsection
