<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $conversation->title ?? 'Conversation partagée' }} – HR Chatbot</title>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@2.0.8/dist/lottie-player.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@4/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body class="layout-chat" data-theme="dark">
    <div class="shared-conversation">
        <!-- Header -->
        <header class="shared-header">
            <a href="{{ route('dashboard') }}" class="shared-brand">
                <lottie-player src="/animations/logo.json" background="transparent" speed="1"
                    style="width: 32px; height: 32px;" loop autoplay>
                </lottie-player>
                <span>HR Chatbot</span>
            </a>
            <div class="shared-badge">
                🔗 Conversation partagée — Lecture seule
            </div>
            <a href="{{ route('login') }}" class="btn btn-primary shared-cta">
                Créer un compte
            </a>
        </header>

        <!-- Titre -->
        <div class="shared-title">
            <h1>{{ $conversation->title ?? 'Conversation sans titre' }}</h1>
        </div>

        <!-- Messages -->
        <div class="shared-messages" id="sharedMessages">
            @foreach($messages as $message)
                <div class="message {{ $message->role }}">
                    <div class="message-avatar" style="background: {{ $message->role === 'user' ? 'var(--primary)' : 'var(--bg-message-ai)' }};">
                        @if($message->role === 'assistant')
                            <lottie-player src="/animations/logo.json" background="transparent" speed="1"
                                style="width: 36px; height: 36px;" loop autoplay>
                            </lottie-player>
                        @else
                            <span class="shared-user-initial">U</span>
                        @endif
                    </div>
                    <div class="message-content" data-role="{{ $message->role }}" data-content="{{ htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8') }}">
                        @if($message->image_url)
                            <img src="{{ $message->image_url }}" class="message-image" alt="Image">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Footer -->
        <footer class="shared-footer">
            <p>Créé avec <a href="{{ route('dashboard') }}">HR Chatbot</a> &middot; <a href="{{ route('login') }}">Rejoindre gratuitement</a></p>
        </footer>
    </div>

    <script>
        // Markdown config identique à chat.blade.php
        marked.setOptions({
            highlight: function(code, lang) {
                if (lang && hljs.getLanguage(lang)) {
                    return hljs.highlight(code, { language: lang }).value;
                }
                return hljs.highlightAuto(code).value;
            },
            breaks: true,
            gfm: true
        });

        function formatMarkdown(text) {
            if (!text) return '';
            let html = marked.parse(text);
            html = html.replace(/<pre><code class="language-(\w+)">/g,
                '<div class="code-block-wrapper"><button class="copy-btn" onclick="copySharedCode(this)">Copier</button><pre><code class="language-$1">'
            );
            html = html.replace(/<pre><code>/g,
                '<div class="code-block-wrapper"><button class="copy-btn" onclick="copySharedCode(this)">Copier</button><pre><code>'
            );
            html = html.replace(/<\/code><\/pre>/g, '</code></pre></div>');
            return html;
        }

        function copySharedCode(button) {
            const code = button.parentElement.querySelector('code');
            navigator.clipboard.writeText(code.textContent).then(() => {
                button.textContent = 'Copié !';
                setTimeout(() => { button.textContent = 'Copier'; }, 2000);
            });
        }

        // Rendre le markdown pour chaque message
        document.querySelectorAll('.message-content[data-content]').forEach(div => {
            const role = div.dataset.role;
            const raw = div.dataset.content;
            if (role === 'assistant') {
                div.innerHTML = (div.querySelector('img')?.outerHTML || '') + formatMarkdown(raw);
            } else {
                const img = div.querySelector('img');
                const escaped = raw.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                const textDiv = document.createElement('div');
                textDiv.textContent = escaped;
                div.innerHTML = (img?.outerHTML || '') + textDiv.innerHTML;
            }
        });
    </script>

    <style>
        .shared-conversation {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem 4rem;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .shared-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .shared-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.1rem;
        }
        .shared-badge {
            flex: 1;
            font-size: 0.85rem;
            color: var(--text-muted);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 0.3rem 0.9rem;
            text-align: center;
        }
        .shared-cta {
            font-size: 0.85rem;
            padding: 0.4rem 1rem;
            white-space: nowrap;
        }
        .shared-title {
            margin-bottom: 2rem;
        }
        .shared-title h1 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .shared-messages {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            flex: 1;
        }
        .shared-user-initial {
            font-weight: 700;
            color: #fff;
            font-size: 0.9rem;
        }
        .shared-footer {
            margin-top: 4rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        .shared-footer a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
</body>

</html>
