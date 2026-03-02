@extends('layouts.app')

@section('title', 'Suppresseur d\'arrière-plan')
@section('page-title', 'Outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Suppresseur d'arrière-plan</h1>
        <p class="page-header__subtitle">Supprimez l'arrière-plan de vos images pour un rendu professionnel.</p>
    </div>
</div>

<div class="bg-remover">

    {{-- Zone upload --}}
    <div class="bg-remover__upload">
        <form id="bg-remover-form" action="{{ route('tools.background-remover.remove') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label class="upload-zone" id="upload-zone">
                <input type="file" name="image" id="image-input" accept=".jpg,.jpeg,.png,.webp" hidden>
                <div class="upload-zone__content" id="upload-placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p>Glissez une image ou <span>cliquez pour parcourir</span></p>
                    <small>JPG, PNG, WEBP — max 10 Mo</small>
                </div>
                <img id="image-preview" src="" alt="Aperçu" hidden>
            </label>

            <div class="bg-remover__actions">
                <button type="submit" class="btn btn--primary" id="submit-btn" disabled>
                    Supprimer l'arrière-plan
                </button>
                <button type="button" class="btn btn--ghost" id="reset-btn" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                    </svg>
                    Nouvelle image
                </button>
            </div>
        </form>
    </div>

    {{-- Résultat --}}
    <div class="bg-remover__result" id="result-zone" hidden>
        <h2>Résultat</h2>
        <div class="result-preview">
            <div class="result-preview__item">
                <span>Original</span>
                <img id="original-preview" src="" alt="Original">
            </div>
            <div class="result-preview__item">
                <span>Sans arrière-plan</span>
                <img id="result-preview-img" src="" alt="Résultat">
            </div>
        </div>
        <a id="download-btn" href="#" class="btn btn--primary" download="sans-fond.png">
            Télécharger
        </a>
    </div>

</div>

@endsection
