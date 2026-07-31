{{--
    Amorçage du thème — à inclure dans <head>, AVANT tout rendu.

    Deux attributs distincts sur <html> :
      data-theme-pref  la PRÉFÉRENCE ("system", "nord", …) — ce que l'utilisateur a choisi
      data-theme       le thème RÉSOLU ("dark", "nord", …) — ce que le CSS applique

    Ils diffèrent uniquement quand la préférence vaut "system" : le CSS n'a
    pas de sélecteur [data-theme="system"], il faut donc trancher en JS.

    Pour un utilisateur connecté, la préférence est rendue côté serveur : aucun
    flash. Sur les pages publiques (connexion…), on retombe sur localStorage.
--}}
@php
    $themePref = auth()->check() ? auth()->user()->effectiveTheme() : null;
@endphp

<script>
    (function () {
        var el = document.documentElement;

        // Le serveur fait autorité s'il a rendu une préférence ; sinon localStorage.
        var pref = @json($themePref) || localStorage.getItem('hub-theme') || 'system';

        var resolved = pref;
        if (pref === 'system') {
            resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        el.setAttribute('data-theme-pref', pref);
        el.setAttribute('data-theme', resolved);

        // Garde localStorage en phase pour que les pages publiques
        // (connexion, mot de passe oublié) affichent le bon thème.
        try { localStorage.setItem('hub-theme', pref); } catch (e) {}
    })();
</script>
