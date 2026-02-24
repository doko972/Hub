<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/js/app.js'])
</head>
<body>

<div class="auth-page">
    <div class="auth-card">

        {{-- Logo --}}
        <div class="auth-card__logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z"/></svg>
            </div>
            <span class="logo-name">Hub</span>
        </div>

        <h1 class="auth-card__title">Connexion</h1>
        <p class="auth-card__subtitle">Accédez à votre espace de travail</p>

        {{-- Erreurs --}}
        @if($errors->any())
            <div class="alert alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
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

            <div class="form-group">
                <label class="form-label" for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control {{ $errors->has('password') ? 'form-control--error' : '' }}"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-toggle">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label">Se souvenir de moi</span>
                </label>
            </div>

            <button type="submit" class="btn btn--primary btn--full" style="margin-top: 8px;">
                Se connecter
            </button>
        </form>

    </div>
</div>

</body>
</html>
