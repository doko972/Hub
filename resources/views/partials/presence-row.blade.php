{{--
    Contenu d'une ligne du panneau de présence : avatar, nom, dernière vue.

    Extrait de sidebar-presence.blade.php pour n'exister qu'une fois, la ligne
    étant enveloppée soit dans un bouton (ouvrir la conversation), soit dans un
    simple bloc (soi-même).

    Ce balisage est dupliqué dans resources/js/components/presence.js, qui
    reconstruit la liste à chaque rafraîchissement : garder les deux en phase.

    Variable attendue : $person (une entrée de App\Services\Presence::roster).
--}}
<span class="presence__avatar">
    @if($person['avatar'])
        <img src="{{ $person['avatar'] }}" alt="">
    @else
        {{ $person['initials'] }}
    @endif
    <span class="presence__dot" aria-hidden="true"></span>
</span>
<span class="presence__body">
    <span class="presence__name">
        {{ $person['name'] }}@if($person['is_self']) <em>(vous)</em>@endif
    </span>
    @unless($person['is_online'])
        <span class="presence__meta">{{ $person['seen_ago'] }}</span>
    @endunless
</span>
