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
            data-direct-url="{{ route('messages.direct', '__ID__') }}"
            aria-live="polite">
            @forelse($roster as $person)
                <li class="presence__item {{ $person['is_online'] ? 'is-online' : '' }}">
                    {{-- Le contenu de la ligne est identique dans les deux cas ;
                         seule l'enveloppe change, car on ne s'écrit pas à
                         soi-même. --}}
                    @php
                        $contenu = view('partials.presence-row', ['person' => $person]);
                    @endphp

                    @if($person['is_self'])
                        <span class="presence__row">{!! $contenu !!}</span>
                    @else
                        {{-- POST et non lien : ouvrir une conversation peut la
                             créer, ce qui n'a pas sa place dans un GET. --}}
                        <form method="POST" action="{{ route('messages.direct', $person['id']) }}">
                            @csrf
                            <button type="submit" class="presence__row"
                                    title="Écrire à {{ $person['name'] }}">{!! $contenu !!}</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="presence__empty" data-presence-empty>Personne d'autre pour l'instant.</li>
            @endforelse
        </ul>
    </div>
</div>
