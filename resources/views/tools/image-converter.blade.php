@extends('layouts.app')

@section('title', 'Convertisseur d\'images')
@section('page-title', 'Outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Convertisseur d'images</h1>
        <p class="page-header__subtitle">Convertissez vos images en différents formats facilement</p>
    </div>
</div>

<div class="img-converter">

    {{-- Zone de dépôt --}}
    <div class="drop-zone" id="dropZone">
        <div class="drop-icon">📸</div>
        <div class="drop-text">Déposez vos images ici</div>
        <div class="drop-subtext">ou cliquez pour sélectionner des fichiers</div>
    </div>

    <input type="file" id="fileInput" accept="image/*" multiple hidden>

    <button class="btn btn--ghost" id="file-picker-btn">
        Choisir des fichiers
    </button>

    {{-- Formats supportés --}}
    <div class="formats-info">
        <div class="formats-title">Formats supportés :</div>
        <div class="formats-list">JPG, PNG, WebP, GIF, BMP, TIFF, SVG</div>
    </div>

    {{-- Fichiers sélectionnés + options --}}
    <div class="files-container" id="filesContainer" style="display:none;">
        <div class="files-header">
            <span>Images sélectionnées</span>
            <button class="btn-small" id="clear-all-btn">Tout effacer</button>
        </div>
        <div id="filesList"></div>

        <div class="conversion-options">
            <div class="options-title">Options de conversion</div>

            <div class="option-group">
                <label class="option-label" for="formatSelect">Format de sortie :</label>
                <select id="formatSelect" class="format-selector">
                    <option value="webp">WebP (Recommandé — Meilleure compression)</option>
                    <option value="jpg">JPG (Compatible partout)</option>
                    <option value="png">PNG (Transparence supportée)</option>
                    <option value="gif">GIF (Animations supportées)</option>
                    <option value="bmp">BMP (Format non compressé)</option>
                    <option value="tiff">TIFF (Haute qualité)</option>
                </select>
                <div id="formatInfo" class="format-info" style="display:none;"></div>
            </div>

            <div class="option-group quality-container" id="qualityContainer">
                <label class="option-label" for="qualitySlider">Qualité :</label>
                <input type="range" id="qualitySlider" class="quality-slider" min="10" max="100" value="85">
                <div class="quality-display" id="qualityDisplay">85%</div>
            </div>

            <button class="convert-btn" id="convertBtn">
                🔄 Convertir les images
            </button>

            <div class="progress-container" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">Conversion en cours...</div>
            </div>

            <div class="results-container" id="resultsContainer">
                <div class="results-title">✅ Conversion terminée !</div>
                <div id="downloadList"></div>
                <button class="download-all-btn" id="download-all-btn">
                    📦 Télécharger tout (ZIP)
                </button>
            </div>
        </div>
    </div>

</div>

@endsection
