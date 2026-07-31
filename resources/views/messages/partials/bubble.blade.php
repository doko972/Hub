{{--
    Une bulle de message.

    Le balisage est reproduit à l'identique en JS (components/messages.js) pour
    les messages arrivant par sondage : garder les deux en phase.
--}}
@php $mine = $message->user_id === auth()->id(); @endphp

<div class="bubble {{ $mine ? 'is-mine' : '' }}" data-message-id="{{ $message->id }}">
    @unless($mine)
        <span class="bubble__avatar">
            @if($message->author?->avatarUrl())
                <img src="{{ $message->author->avatarUrl() }}" alt="">
            @else
                {{ $message->author?->initials() ?? '?' }}
            @endif
        </span>
    @endunless

    <div class="bubble__content">
        @unless($mine)
            <span class="bubble__author">{{ $message->author?->name ?? 'Utilisateur supprimé' }}</span>
        @endunless

        {{-- Texte brut volontairement : pas de Markdown ni de HTML dans la
             messagerie interne, donc aucune surface d'injection. --}}
        <p class="bubble__body">{{ $message->body }}</p>

        <span class="bubble__time">{{ $message->created_at->locale('fr')->isoFormat('D MMM à HH:mm') }}</span>
    </div>
</div>
