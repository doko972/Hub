<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — Hub</title>
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

        <h1 class="auth-card__title">Mot de passe oublié</h1>
        <p class="auth-card__subtitle">Renseignez votre email pour recevoir un lien de réinitialisation.</p>

        {{-- Message de succès --}}
        @if(session('status'))
            <div class="alert alert--success" style="margin-bottom: 20px;">
                {{ session('status') }}
            </div>
        @endif

        {{-- Erreurs --}}
        @if($errors->any())
            <div class="alert alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="email">Adresse email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control {{ $errors->has('email') ? 'form-control--error' : '' }}"
                    value="{{ old('email') }}"
                    placeholder="vous@exemple.com"
                    autocomplete="email"
                    autofocus
                    required
                >
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top: 8px;">
                Envoyer le lien
            </button>
        </form>

        <div class="auth-footer">
            <a href="{{ route('login') }}" class="auth-link">← Retour à la connexion</a>
        </div>

    </div>
</div>

</body>
</html>
