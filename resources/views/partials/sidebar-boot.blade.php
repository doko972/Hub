{{--
    Amorçage des sections repliables de la sidebar — à inclure dans <head>,
    AVANT tout rendu, sinon une section repliée s'afficherait déployée le temps
    que le JS s'exécute.

    L'état vit dans localStorage et est appliqué en classes sur <html>
    (is-sidebar-collapsed-tools, …) : le CSS peut ainsi replier une section
    avant même que la sidebar n'existe dans le DOM.

    La section contenant la page courante est toujours redéployée : arriver sur
    une page dont le lien est masqué serait déroutant. L'utilisateur reste libre
    de la replier ensuite.
--}}
@php
    $activeSidebarSection = match (true) {
        request()->routeIs('tools.*') => 'tools',
        request()->routeIs('admin.*') => 'admin',
        default                       => null,
    };
@endphp

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        var KEY = 'hub.sidebar.collapsed';
        var active = @json($activeSidebarSection);
        var collapsed = [];

        try {
            collapsed = JSON.parse(localStorage.getItem(KEY)) || [];
        } catch (e) {
            collapsed = [];
        }

        if (!Array.isArray(collapsed)) {
            collapsed = [];
        }

        if (active && collapsed.indexOf(active) !== -1) {
            collapsed = collapsed.filter(function (key) { return key !== active; });
            try { localStorage.setItem(KEY, JSON.stringify(collapsed)); } catch (e) {}
        }

        collapsed.forEach(function (key) {
            document.documentElement.classList.add('is-sidebar-collapsed-' + key);
        });
    })();
</script>
