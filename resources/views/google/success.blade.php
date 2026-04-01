<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar connecté</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="layout-auth">
    <div class="google-success">
        <div class="icon">✅</div>
        <h1>Google Calendar connecté !</h1>
        <p>Fermeture en cours…</p>
    </div>
    <script>
        // Fermer le popup automatiquement après connexion
        setTimeout(() => window.close(), 800);
    </script>
</body>
</html>