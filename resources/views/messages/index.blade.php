@extends('layouts.app')

@section('title', 'Messagerie')
@section('page-title', 'Messagerie')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Messagerie</h1>
        <p class="page-header__subtitle">Échangez avec vos collègues, à deux ou en groupe.</p>
    </div>
    <div class="page-header__actions">
        {{-- Masqué par défaut : le JS ne l'affiche que si le navigateur sait
             réellement recevoir des notifications. --}}
        <button type="button" class="btn btn--ghost push-toggle"
                data-push-toggle
                data-vapid-url="{{ route('push.vapid') }}"
                data-subscribe-url="{{ route('push.subscribe') }}"
                data-unsubscribe-url="{{ route('push.unsubscribe') }}"
                hidden>🔕 Activer les notifications</button>

        <button type="button" class="btn btn--ghost push-toggle" data-sound-toggle>🔊 Son activé</button>

    <button type="button" class="btn btn--primary" data-new-discussion>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nouvelle discussion
    </button>
    </div>
</div>

<div class="messenger">

    {{-- ===== Colonne gauche : les fils ===== --}}
    <aside class="messenger__list" aria-label="Discussions">
        @forelse($discussions as $thread)
            @php
                $count = $unread[$thread->id] ?? 0;
                $other = $thread->counterpartFor(auth()->id());
            @endphp
            <a href="{{ route('messages.show', $thread) }}"
               class="thread {{ $discussion && $discussion->id === $thread->id ? 'is-current' : '' }} {{ $count ? 'has-unread' : '' }}">

                <span class="thread__avatar">
                    @if($thread->is_group)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    @elseif($other?->avatarUrl())
                        <img src="{{ $other->avatarUrl() }}" alt="">
                    @else
                        {{ $other?->initials() ?? '?' }}
                    @endif

                    @if($other && $other->isOnline())
                        <span class="thread__dot" title="En ligne"></span>
                    @endif
                </span>

                <span class="thread__body">
                    <span class="thread__title">{{ $thread->titleFor(auth()->id()) }}</span>
                    <span class="thread__preview">
                        @if($thread->lastMessage)
                            {{ \Illuminate\Support\Str::limit($thread->lastMessage->body, 48) }}
                        @else
                            <em>Aucun message</em>
                        @endif
                    </span>
                </span>

                @if($count)
                    <span class="thread__badge">{{ $count > 99 ? '99+' : $count }}</span>
                @endif
            </a>
        @empty
            <p class="messenger__empty">Aucune discussion pour l'instant.</p>
        @endforelse
    </aside>

    {{-- ===== Colonne droite : le fil ouvert ===== --}}
    <section class="messenger__thread">
        @if($discussion)
            <header class="messenger__header">
                <div>
                    <h2>{{ $discussion->titleFor(auth()->id()) }}</h2>
                    <p>
                        @if($discussion->is_group)
                            {{ $discussion->participants->count() }} participants :
                            {{ $discussion->participants->pluck('name')->join(', ') }}
                        @elseif($discussion->counterpartFor(auth()->id())?->isOnline())
                            En ligne
                        @else
                            Hors ligne
                        @endif
                    </p>
                </div>

                @can('leave', $discussion)
                    <form method="POST" action="{{ route('messages.leave', $discussion) }}"
                          data-confirm="Quitter cette discussion ?">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">Quitter</button>
                    </form>
                @endcan
            </header>

            <div class="messenger__messages"
                 data-messages
                 data-poll-url="{{ route('messages.poll', $discussion) }}"
                 data-send-url="{{ route('messages.send', $discussion) }}"
                 data-last-id="{{ $messages->last()->id ?? 0 }}"
                 data-poll-interval="{{ (int) config('messaging.thread_poll_seconds', 5) }}"
                 {{-- __ID__ est remplacé côté client par l'identifiant du message --}}
                 data-reaction-url="{{ route('messages.reactions.toggle', [$discussion, '__ID__']) }}"
                 data-message-url="{{ route('messages.messages.update', [$discussion, '__ID__']) }}"
                 data-typing-url="{{ route('messages.typing', $discussion) }}"
                 aria-live="polite">
                @foreach($messages as $message)
                    @include('messages.partials.bubble', ['message' => $message])
                @endforeach
            </div>

            {{-- Indicateur de frappe, alimenté par le sondage du fil --}}
            <p class="typing-indicator" data-typing-indicator hidden>
                <span class="typing-indicator__text" data-typing-text></span>
                <span class="typing-indicator__dots" aria-hidden="true"><i></i><i></i><i></i></span>
            </p>

            <form class="messenger__composer" data-composer
                  data-max-files="{{ (int) config('messaging.attachments.max_files') }}"
                  data-max-size-kb="{{ (int) config('messaging.attachments.max_size_kb') }}">
                @csrf

                {{-- Fichiers choisis, avant envoi --}}
                <ul class="composer-files" data-file-list hidden></ul>

                {{-- Panneau GIF, alimenté par la recherche relayée --}}
                @if(\App\Services\GifSearch::isConfigured())
                    <div class="gif-panel" data-gif-panel
                         data-search-url="{{ route('messages.gifs.search') }}"
                         data-send-url="{{ route('messages.gif.send', $discussion) }}" hidden>
                        <input type="search" class="gif-panel__search" data-gif-search
                               placeholder="Rechercher un GIF…" aria-label="Rechercher un GIF">
                        <div class="gif-panel__results" data-gif-results></div>
                        <p class="gif-panel__credit">GIF fournis par GIPHY</p>
                    </div>
                @endif

                <div class="composer-row">
                    @if(\App\Services\GifSearch::isConfigured())
                        <button type="button" class="composer-btn" data-gif-toggle title="Envoyer un GIF">GIF</button>
                    @endif

                    <button type="button" class="composer-btn" data-emoji-toggle title="Émoticones">😊</button>

                    <label class="composer-attach" title="Joindre un fichier">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        <span class="sr-only">Joindre un fichier</span>
                        <input type="file" name="attachments[]" multiple data-file-input
                               accept=".{{ implode(',.', config('messaging.attachments.allowed_extensions')) }}">
                    </label>

                    <label class="sr-only" for="message-body">Votre message</label>
                    <textarea id="message-body" name="body" rows="1" maxlength="5000"
                              placeholder="Écrivez votre message…  (Entrée pour envoyer)"></textarea>

                    <button type="submit" class="btn btn--primary btn--sm">Envoyer</button>
                </div>
            </form>
        @else
            <div class="messenger__placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                </svg>
                <p>Choisissez une discussion, ou démarrez-en une nouvelle.</p>
            </div>
        @endif
    </section>
</div>

{{-- ===== Modale de création ===== --}}
<div class="new-discussion" data-new-discussion-modal hidden>
    <div class="new-discussion__panel" role="dialog" aria-modal="true" aria-labelledby="new-discussion-title">
        <h2 id="new-discussion-title">Nouvelle discussion</h2>

        <div class="new-discussion__tabs" role="tablist">
            <button type="button" class="is-active" data-tab="direct" role="tab">À deux</button>
            <button type="button" data-tab="group" role="tab">Groupe</button>
        </div>

        {{-- Conversation directe --}}
        <div data-panel="direct">
            <ul class="contact-list">
                @forelse($contacts as $contact)
                    <li>
                        <form method="POST" action="{{ route('messages.direct', $contact) }}">
                            @csrf
                            <button type="submit" class="contact">
                                <span class="contact__avatar">
                                    @if($contact->avatarUrl())
                                        <img src="{{ $contact->avatarUrl() }}" alt="">
                                    @else
                                        {{ $contact->initials() }}
                                    @endif
                                    @if($contact->isOnline())<span class="contact__dot"></span>@endif
                                </span>
                                <span>{{ $contact->name }}</span>
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="contact-list__empty">Aucun autre utilisateur actif.</li>
                @endforelse
            </ul>
        </div>

        {{-- Groupe --}}
        <div data-panel="group" hidden>
            <form method="POST" action="{{ route('messages.groups.store') }}">
                @csrf
                <label class="field">
                    <span class="field__label">Nom du groupe</span>
                    <input type="text" name="name" maxlength="80" required placeholder="Ex. Équipe support">
                </label>

                <span class="field__label">Participants</span>
                <ul class="contact-list contact-list--checkable">
                    @foreach($contacts as $contact)
                        <li>
                            <label class="contact">
                                <input type="checkbox" name="members[]" value="{{ $contact->id }}">
                                <span class="contact__avatar">
                                    @if($contact->avatarUrl())
                                        <img src="{{ $contact->avatarUrl() }}" alt="">
                                    @else
                                        {{ $contact->initials() }}
                                    @endif
                                </span>
                                <span>{{ $contact->name }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <div class="new-discussion__actions">
                    <button type="button" class="btn btn--ghost btn--sm" data-close-modal>Annuler</button>
                    <button type="submit" class="btn btn--primary btn--sm">Créer le groupe</button>
                </div>
            </form>
        </div>

        <button type="button" class="new-discussion__close" data-close-modal aria-label="Fermer">×</button>
    </div>
</div>

@if($errors->any())
    <div class="alert alert--danger" style="margin-top:1rem;">
        {{ $errors->first() }}
    </div>
@endif

@endsection
