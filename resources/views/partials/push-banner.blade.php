{{--
    Invitation à activer les notifications du navigateur.

    Un bouton discret dans l'en-tête d'une seule page ne suffit pas : l'abonnement
    ne peut pas être fait à la place de l'utilisateur, chacun doit cliquer une
    fois. Ce bandeau rend la démarche visible depuis n'importe quelle page.

    Rendu uniquement si le serveur sait envoyer des notifications ; le JS le
    masque ensuite selon l'état réel du navigateur (déjà autorisé, refusé, ou
    non pris en charge).
--}}
@if(\App\Services\PushService::isConfigured())
    <div class="push-banner" data-push-banner
         data-vapid-url="{{ route('push.vapid') }}"
         data-subscribe-url="{{ route('push.subscribe') }}"
         hidden>
        <span class="push-banner__icon" aria-hidden="true">🔔</span>

        <div class="push-banner__text">
            <strong>Activez les notifications</strong>
            <span>Recevez les nouveaux messages même lorsque le Hub n'est pas à l'écran.</span>
        </div>

        <div class="push-banner__actions">
            <button type="button" class="btn btn--ghost btn--sm" data-push-later>Plus tard</button>
            <button type="button" class="btn btn--primary btn--sm" data-push-enable>Activer</button>
        </div>
    </div>
@endif
