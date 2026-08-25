{{--
    Paramètres de notification, à inclure dans le <head> de TOUT gabarit.

    Ils étaient auparavant portés par la pastille de la sidebar : les pages qui
    n'utilisent pas le gabarit principal — le chat IA notamment — ne recevaient
    donc ni fenêtre, ni son, ni compteur d'onglet. Passer par des balises meta
    découple la notification de l'endroit où le compteur s'affiche.
--}}
<meta name="messages-unread-url" content="{{ route('messages.unread') }}">
<meta name="messages-unread-interval" content="{{ (int) config('messaging.unread_poll_seconds', 20) }}">
