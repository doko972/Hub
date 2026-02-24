@extends('layouts.app')

@section('title', 'Mon profil')
@section('page-title', 'Mon profil')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Mon profil</h1>
        <p class="page-header__subtitle">Gérez vos informations personnelles et votre photo.</p>
    </div>
</div>

<div class="profile-layout">

    {{-- ===== COLONNE GAUCHE : Avatar ===== --}}
    <div class="profile-avatar-col">
        <div class="card">
            <div class="card__body" style="text-align: center;">

                {{-- Affichage de l'avatar --}}
                <div class="avatar-display">
                    @if($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}"
                             alt="{{ $user->name }}"
                             class="avatar-display__img"
                             id="avatar-preview">
                    @else
                        <div class="avatar-display__initials" id="avatar-initials">
                            {{ $user->initials() }}
                        </div>
                        <img src="" alt="" class="avatar-display__img" id="avatar-preview" style="display:none;">
                    @endif
                </div>

                <h2 style="font-size: 18px; font-weight: 700; margin: 16px 0 4px;">{{ $user->name }}</h2>
                <p style="font-size: 13px; color: #6B7280; margin-bottom: 20px;">
                    {{ $user->isAdmin() ? 'Administrateur' : 'Utilisateur' }}
                </p>

                {{-- Upload avatar --}}
                <form method="POST"
                      action="{{ route('profile.avatar') }}"
                      enctype="multipart/form-data"
                      id="avatar-form">
                    @csrf

                    <label class="btn btn--primary btn--full" for="avatar-input" style="cursor:pointer; margin-bottom: 10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Choisir une photo
                    </label>
                    <input type="file"
                           id="avatar-input"
                           name="avatar"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           style="display:none;"
                           data-image-preview="avatar-preview"
                           data-auto-submit="avatar-form">

                    @error('avatar')
                        <span class="form-error" style="justify-content:center;">{{ $message }}</span>
                    @enderror
                </form>

                {{-- Supprimer l'avatar --}}
                @if($user->avatarUrl())
                    <form method="POST"
                          action="{{ route('profile.avatar.delete') }}"
                          data-confirm="Supprimer votre photo de profil ?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn--ghost btn--full" style="color:#EF4444;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14H6L5 6"/>
                                <path d="M10 11v6"/><path d="M14 11v6"/>
                                <path d="M9 6V4h6v2"/>
                            </svg>
                            Supprimer la photo
                        </button>
                    </form>
                @endif

                <p style="font-size: 11px; color: #9CA3AF; margin-top: 12px;">
                    JPG, PNG, GIF ou WebP — max 2 Mo
                </p>

            </div>
        </div>
    </div>

    {{-- ===== COLONNE DROITE : Informations ===== --}}
    <div class="profile-info-col">
        <div class="card">
            <div class="card__header">
                <h2 class="card__title">Informations personnelles</h2>
            </div>
            <div class="card__body">

                @if(session('success'))
                    <div class="alert alert--success">{{ session('success') }}</div>
                @endif

                @if($errors->any() && !$errors->has('avatar'))
                    <div class="alert alert--error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" novalidate>
                    @csrf @method('PUT')

                    <div class="form-group">
                        <label class="form-label" for="name">Nom complet</label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control {{ $errors->has('name') ? 'form-control--error' : '' }}"
                               value="{{ old('name', $user->name) }}"
                               required>
                        @error('name')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Adresse email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control {{ $errors->has('email') ? 'form-control--error' : '' }}"
                               value="{{ old('email', $user->email) }}"
                               required>
                        @error('email')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 24px 0;">

                    <p style="font-size: 14px; font-weight: 600; margin-bottom: 16px; color: #374151;">
                        Changer le mot de passe
                        <span style="font-weight: 400; color: #9CA3AF; font-size: 12px;">(laisser vide pour ne pas modifier)</span>
                    </p>

                    <div class="form-row form-row--2">
                        <div class="form-group">
                            <label class="form-label" for="password">Nouveau mot de passe</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control {{ $errors->has('password') ? 'form-control--error' : '' }}"
                                   placeholder="Minimum 8 caractères"
                                   autocomplete="new-password">
                            @error('password')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password_confirmation">Confirmer</label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   class="form-control"
                                   placeholder="Répétez le mot de passe"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 8px;">
                        <button type="submit" class="btn btn--primary">
                            Enregistrer les modifications
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- Infos lecture seule --}}
        <div class="card" style="margin-top: 20px;">
            <div class="card__header">
                <h2 class="card__title">Informations du compte</h2>
            </div>
            <div class="card__body">
                <div style="display: flex; flex-direction: column; gap: 14px;">

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                        <span style="color: #6B7280;">Rôle</span>
                        @if($user->isAdmin())
                            <span class="badge badge--admin">Administrateur</span>
                        @else
                            <span class="badge badge--user">Utilisateur</span>
                        @endif
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                        <span style="color: #6B7280;">Statut</span>
                        <span class="badge badge--active">Actif</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                        <span style="color: #6B7280;">Membre depuis</span>
                        <span style="font-weight: 500;">{{ $user->created_at->format('d/m/Y') }}</span>
                    </div>

                    @if(!$user->isAdmin())
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px;">
                            <span style="color: #6B7280;">Outils accessibles</span>
                            <span style="font-weight: 500;">{{ $user->tools->count() }} outil(s)</span>
                        </div>
                    @endif

                </div>
            </div>
        </div>

    </div>

</div>

@endsection
