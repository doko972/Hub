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
        @if(filled($message->body))
            <p class="bubble__body">{{ $message->body }}</p>
        @endif

        @if($message->attachments->isNotEmpty())
            <div class="attachments">
                @foreach($message->attachments as $file)
                    @if($file->isInlineImage())
                        <a href="{{ $file->url ?? route('messages.attachment', $file) }}"
                           class="attachments__image" target="_blank" rel="noopener">
                            <img src="{{ route('messages.attachment', $file) }}"
                                 alt="{{ $file->original_name }}" loading="lazy">
                        </a>
                    @else
                        <a href="{{ route('messages.attachment', $file) }}" class="attachments__file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <span class="attachments__name">{{ $file->original_name }}</span>
                            <span class="attachments__size">{{ $file->humanSize() }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        <span class="bubble__time">
            {{ $message->created_at->locale('fr')->isoFormat('D MMM à HH:mm') }}
            @if($message->edited_at)
                <em class="bubble__edited">· modifié</em>
            @endif
        </span>

        {{-- Réactions posées. Le conteneur existe toujours : le JS y écrit
             sans avoir à créer de structure. --}}
        <div class="reactions" data-reactions>
            @foreach($message->reactionSummary(auth()->id()) as $reaction)
                <button type="button"
                        class="reaction {{ $reaction['mine'] ? 'is-mine' : '' }}"
                        data-emoji="{{ $reaction['emoji'] }}">
                    <span class="reaction__emoji">{{ $reaction['emoji'] }}</span>
                    <span class="reaction__count">{{ $reaction['count'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if($mine)
        <div class="bubble__actions">
            @if(filled($message->body))
                <button type="button" data-edit-trigger aria-label="Modifier" title="Modifier">✏️</button>
            @endif
            <button type="button" data-delete-trigger aria-label="Supprimer" title="Supprimer">🗑</button>
        </div>
    @endif

    <button type="button" class="bubble__react" data-react-trigger aria-label="Réagir">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
            <circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/>
            <line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
        </svg>
    </button>
</div>
