{{--
    Panneau de présence de la sidebar.

    Le premier rendu est fait côté serveur (aucune attente au chargement), puis
    resources/js/components/presence.js rafraîchit la liste périodiquement.
    Le balisage d'une ligne est dupliqué en JS : garder les deux en phase.
--}}
@php
    $roster       = \App\Services\Presence::roster(auth()->id());
    $onlineCount  = \App\Services\Presence::onlineCount();
@endphp

<hr class="sidebar__divider">

<div class="sidebar__section" data-sidebar-section="presence">
    <button type="button"
            class="sidebar__section-toggle"
            data-sidebar-toggle="presence"
            aria-expanded="true"
            aria-controls="sidebar-section-presence">
        <span class="sidebar__section-title">
            En ligne (<span data-presence-count>{{ $onlineCount }}</span>)
        </span>
        <svg class="sidebar__section-chevron" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    <div class="sidebar__section-body" id="sidebar-section-presence">
        <ul class="presence" data-presence-list
            data-presence-url="{{ route('presence.index') }}"
            data-presence-interval="{{ (int) config('presence.poll_seconds', 30) }}"
            aria-live="polite">
            @forelse($roster as $person)
                <li class="presence__item {{ $person['is_online'] ? 'is-online' : '' }}">
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
                </li>
            @empty
                <li class="presence__empty" data-presence-empty>Personne d'autre pour l'instant.</li>
            @endforelse
        </ul>
    </div>
</div>
