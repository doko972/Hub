<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe — Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/js/app.js'])
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>

    {{-- Anti-flash thème --}}
    <script>
        (function () {
            var saved = localStorage.getItem('hub-theme');
            var sys   = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if ((saved || sys) === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<div class="auth-page">
    <div class="auth-card">

        {{-- Logo --}}
        <div class="auth-card__logo">
            <dotlottie-player class="brand-lottie brand-lottie--light"
                src="{{ asset('logo.json') }}"
                background="transparent"
                speed="1"
                style="width: 52px; height: 52px;"
                autoplay>
            </dotlottie-player>
            <dotlottie-player class="brand-lottie brand-lottie--dark"
                src="{{ asset('logo-dark.json') }}"
                background="transparent"
                speed="1"
                style="width: 52px; height: 52px;"
                autoplay>
            </dotlottie-player>
            <span class="logo-name">Hub</span>
        </div>

        <h1 class="auth-card__title">Nouveau mot de passe</h1>
        <p class="auth-card__subtitle">Choisissez un mot de passe d'au moins 8 caractères.</p>

        {{-- Erreurs --}}
        @if($errors->any())
            <div class="alert alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label class="form-label" for="email">Adresse email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control {{ $errors->has('email') ? 'form-control--error' : '' }}"
                    value="{{ old('email', $email) }}"
                    placeholder="vous@exemple.com"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Nouveau mot de passe</label>
                <div class="input-password">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control {{ $errors->has('password') ? 'form-control--error' : '' }}"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        autofocus
                        required
                    >
                    <button type="button" class="input-password__toggle" aria-label="Afficher le mot de passe">
                        <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="icon-eye-off hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirmer le mot de passe</label>
                <div class="input-password">
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-control"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="input-password__toggle" aria-label="Afficher le mot de passe">
                        <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg class="icon-eye-off hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top: 8px;">
                Réinitialiser le mot de passe
            </button>
        </form>

        <div class="auth-footer">
            <a href="{{ route('login') }}" class="auth-link">← Retour à la connexion</a>
        </div>

    </div>
</div>

</body>
</html>
