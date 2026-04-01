<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Hub">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>ChatBot</title>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@2.0.8/dist/lottie-player.js"></script>
    <!-- Markdown & Syntax Highlighting -->
    <script src="https://cdn.jsdelivr.net/npm/marked@4/marked.min.js"></script>
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body class="layout-chat {{ request()->query('embedded') ? 'is-embedded' : '' }}">
    <div class="app-container">
        <!-- Overlay mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('dashboard') }}" class="sidebar-brand" title="Retour à l'accueil">
                    <lottie-player src="/animations/logo.json" background="transparent" speed="1"
                        style="width: 36px; height: 36px;" loop autoplay>
                    </lottie-player>
                    <h1>ChatBot</h1>
                </a>
                <button class="btn-sidebar-close" id="btnSidebarClose" title="Fermer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <button class="btn-new-chat" id="btnNewChat">
                <span>+</span> Nouvelle conversation
            </button>

            <!-- Recherche -->
            <div class="sidebar-search">
                <input type="text" id="sidebarSearch" placeholder="🔍 Rechercher..." autocomplete="off">
            </div>

            <!-- Section Dossiers -->
            <div class="folders-section">
                <div class="section-header">
                    <span>Dossiers</span>
                    <button class="btn-add-folder" id="btnAddFolder" title="Nouveau dossier">+</button>
                </div>
                <div class="folders-list" id="foldersList">
                    <!-- Les dossiers seront chargés ici -->
                </div>
            </div>

            <!-- Section Conversations -->
            <div class="conversations-section">
                <div class="section-header">
                    <span>Conversations</span>
                </div>
                <div class="conversations-list" id="conversationsList">
                    <!-- Les conversations seront chargées ici -->
                </div>
            </div>

            {{-- <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn-logout" title="Déconnexion">
                        🚪
                    </button>
                </form>
            </div> --}}
        </aside>

        <!-- Zone de chat -->
        <main class="chat-container">
            <header class="chat-header">
                <button class="btn-menu-mobile" id="btnMenuMobile" title="Menu">
                    ☰
                </button>
                <h2 id="chatTitle">Nouvelle conversation</h2>
                <div class="header-actions">
                    <button class="btn-personality" id="btnPersonality" title="Personnalité IA">
                        🤖
                    </button>
                    <button class="btn-share-conv" id="btnShareConv" title="Partager cette conversation"
                        style="display: none;">
                        🔗
                    </button>
                    <button class="btn-theme" id="btnTheme" title="Changer de thème">
                        🌙
                    </button>
                    <select class="model-selector" id="modelSelector">
                        <option value="gpt-4o" selected>GPT-4o</option>
                        <option value="gpt-4o-mini">GPT-4o Mini</option>
                        <option value="gpt-5-mini">GPT-5 Mini</option>
                        <option value="gpt-5">GPT-5</option>
                        {{-- <option value="claude-sonnet-4-20250514">Claude Sonnet 4</option>
                        <option value="claude-haiku-3-5-20241022">Claude Haiku 3.5</option> --}}
                    </select>
                </div>
            </header>

            <div class="chat-messages" id="chatMessages">
                <!-- Écran d'accueil -->
                <div class="empty-chat" id="emptyChat">
                    <lottie-player src="/animations/logo.json" background="transparent" speed="1"
                        style="width: 80px; height: 80px; margin-bottom: 1rem;" loop autoplay>
                    </lottie-player>
                    <h2>Bonjour, {{ Auth::user()->name }}</h2>
                    <p>Toujours prêt à répondre.</p>
                    {{-- <div class="suggestions">
                        <div class="suggestion-card" data-suggestion="Explique-moi un concept complexe simplement">
                            <div class="icon">💡</div>
                            <div class="text">Explique-moi un concept complexe simplement</div>
                        </div>
                        <div class="suggestion-card" data-suggestion="Aide-moi à rédiger un email professionnel">
                            <div class="icon">✉️</div>
                            <div class="text">Aide-moi à rédiger un email professionnel</div>
                        </div>
                        <div class="suggestion-card" data-suggestion="Génère du code pour mon projet">
                            <div class="icon">💻</div>
                            <div class="text">Génère du code pour mon projet</div>
                        </div>
                        <div class="suggestion-card" data-suggestion="Recherche des informations sur le web">
                            <div class="icon">🔍</div>
                            <div class="text">Recherche des informations sur le web</div>
                        </div>
                        <div class="suggestion-card" data-suggestion="Quels sont mes rendez-vous cette semaine ?">
                            <div class="icon">📅</div>
                            <div class="text">Quels sont mes rendez-vous cette semaine ?</div>
                        </div>
                    </div> --}}
                </div>
            </div>

            <div class="chat-input-container">
                <!-- Aperçu image -->
                <div class="image-preview-container" id="imagePreviewContainer" style="display: none;">
                    <div class="image-preview">
                        <img id="imagePreview" src="" alt="Aperçu">
                        <button class="remove-image-btn" id="removeImageBtn" title="Supprimer l'image">✕</button>
                    </div>
                </div>

                <!-- Chip document PDF -->
                <div class="document-chip-container" id="documentChipContainer" style="display: none;">
                    <div class="document-chip">
                        <span class="document-chip-icon">📄</span>
                        <div class="document-chip-info">
                            <span class="document-chip-name" id="documentChipName"></span>
                            <span class="document-chip-meta" id="documentChipMeta"></span>
                        </div>
                        <button class="document-chip-remove" id="removeDocumentBtn"
                            title="Supprimer le document">✕</button>
                    </div>
                </div>

                <!-- Input file caché (images + PDF) -->
                <input type="file" id="fileInput" accept="image/*,.pdf,application/pdf" style="display: none;">

                <div class="chat-input-wrapper">
                    <div class="input-actions">
                        <button class="btn-actions-menu" id="btnActionsMenu" title="Plus d'options">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                        </button>
                        <div class="actions-dropdown" id="actionsDropdown">
                            <button class="dropdown-item btn-attach" id="btnAttach"
                                title="Joindre un fichier (image ou PDF)">
                                <span class="dropdown-item-icon">📎</span>
                                <span class="dropdown-item-label">Joindre un fichier</span>
                            </button>

                            <button class="dropdown-item btn-imagine" id="btnImagine" title="Générer une image">
                                <span class="dropdown-item-icon">🎨</span>
                                <span class="dropdown-item-label">Générer une image</span>
                            </button>

                            <button class="dropdown-item" id="btnGoogleCalendar" title="Connecter Google Calendar">
                                <span class="dropdown-item-icon">📅</span>
                                <span class="dropdown-item-label">Google Calendar</span>
                                <span class="item-status"></span>
                            </button>

                            <button class="dropdown-item" id="btnPushNotif" title="Activer les notifications">
                                <span class="dropdown-item-icon">🔔</span>
                                <span class="dropdown-item-label">Notifications</span>
                                <span class="item-status"></span>
                            </button>

                            <button class="dropdown-item" id="btnInstallApp" style="display:none;" title="Installer l'app">
                                <span class="dropdown-item-icon">📲</span>
                                <span class="dropdown-item-label">Installer l'app</span>
                            </button>
                        </div>
                    </div>
                    <textarea class="chat-input" id="chatInput" placeholder="Écrivez votre message..."
                        rows="1"></textarea>
                    <button class="btn-mic" id="btnMic" title="Dictée vocale">
                        🎤
                    </button>
                    <button class="btn-send" id="btnSend" title="Envoyer" style="display: none;">
                        ➤
                    </button>
                    <button class="btn-stop" id="btnStop" title="Arrêter la génération" style="display: none;">
                        ⏹
                    </button>
                </div>
                <div class="input-footer">
                    Le Chatbot peut faire des erreurs. Vérifiez les informations importantes.
                </div>
            </div>
        </main>
    </div>
    <!-- Modal génération image -->
    <div class="modal-overlay" id="imagineModal">
        <div class="modal" style="max-width: 500px;">
            <h3>🎨 Générer une image</h3>
            <p style="color: var(--bg); font-size: 0.9rem; margin-bottom: 1rem;">
                Décrivez l'image que vous souhaitez créer avec DALL-E 3
            </p>

            <textarea id="imaginePrompt" placeholder="Ex: Un chat astronaute sur la lune, style art digital..." rows="3"
                style="width: 100%; padding: 0.75rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-light); font-size: 1rem; resize: vertical; margin-bottom: 1rem;"></textarea>

            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Taille</label>
                    <select id="imagineSize"
                        style="width: 100%; padding: 0.5rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-light);">
                        <option value="1024x1024">Carré (1024×1024)</option>
                        <option value="1792x1024">Paysage (1792×1024)</option>
                        <option value="1024x1792">Portrait (1024×1792)</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">Qualité</label>
                    <select id="imagineQuality"
                        style="width: 100%; padding: 0.5rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-light);">
                        <option value="standard">Standard</option>
                        <option value="hd">HD</option>
                    </select>
                </div>
            </div>

            <div id="imagineRemaining" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                <!-- Quota affiché ici -->
            </div>

            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelImagine">Annuler</button>
                <button class="btn-confirm" id="btnConfirmImagine">
                    <span id="imagineButtonText">Générer</span>
                </button>
            </div>
        </div>
    </div>
    <!-- Modal renommer conversation -->
    <div class="modal-overlay" id="renameConversationModal">
        <div class="modal">
            <h3>✏️ Renommer la conversation</h3>
            <input type="text" id="renameConversationInput" placeholder="Nouveau titre" maxlength="100">
            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelRenameConversation">Annuler</button>
                <button class="btn-confirm" id="btnConfirmRenameConversation">Renommer</button>
            </div>
        </div>
    </div>

    <!-- Modal renommer dossier -->
    <div class="modal-overlay" id="renameFolderModal">
        <div class="modal">
            <h3>✏️ Renommer le dossier</h3>
            <input type="text" id="renameFolderInput" placeholder="Nouveau nom" maxlength="50">
            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelRenameFolder">Annuler</button>
                <button class="btn-confirm" id="btnConfirmRenameFolder">Renommer</button>
            </div>
        </div>
    </div>
    <!-- Modal déplacer conversation -->
    <div class="modal-overlay" id="moveModal">
        <div class="modal">
            <h3>📁 Déplacer vers...</h3>
            <div id="moveFoldersList" style="max-height: 300px; overflow-y: auto; margin-bottom: 1rem;">
                <!-- Liste des dossiers -->
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelMove">Annuler</button>
            </div>
        </div>
    </div>
    <!-- Modal création dossier -->
    <div class="modal-overlay" id="folderModal">
        <div class="modal">
            <h3>📁 Nouveau dossier</h3>
            <input type="text" id="folderNameInput" placeholder="Nom du dossier" maxlength="50">
            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelFolder">Annuler</button>
                <button class="btn-confirm" id="btnConfirmFolder">Créer</button>
            </div>
        </div>
    </div>
    <!-- Modal partage de conversation -->
    <div class="modal-overlay" id="shareModal">
        <div class="modal">
            <h3>🔗 Partager la conversation</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                Toute personne disposant du lien pourra lire cette conversation (lecture seule).
            </p>
            <div id="shareUrlGroup" style="display: none; margin-bottom: 1rem;">
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="shareUrlInput" readonly
                        style="flex: 1; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 0.75rem; color: var(--text-primary); font-size: 0.85rem;">
                    <button class="btn-confirm" id="btnCopyShareUrl" style="white-space: nowrap;">Copier</button>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" id="btnCancelShare">Fermer</button>
                <button class="btn-danger" id="btnRevokeShare" style="display: none;">Révoquer</button>
                <button class="btn-confirm" id="btnCreateShare">Générer le lien</button>
            </div>
        </div>
    </div>
    <!-- Modal personnalités IA -->
    <div class="modal-overlay" id="personalityModal">
        <div class="modal" style="max-width: 520px;">
            <h3>🤖 Personnalité IA</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                Choisissez un rôle ou des instructions permanentes pour l'IA.
            </p>

            <!-- Liste des personnalités -->
            <div id="personalityList" style="margin-bottom: 1rem;"></div>

            <!-- Formulaire création / édition (caché par défaut) -->
            <div id="personalityForm"
                style="display: none; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem;">
                <input type="hidden" id="personalityEditId">
                <div style="margin-bottom: 0.75rem;">
                    <label
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.35rem;">Nom
                        de la personnalité</label>
                    <input type="text" id="personalityNameInput" placeholder="Ex: Assistant juridique" maxlength="100"
                        style="width: 100%; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 0.75rem; color: var(--text-primary); font-size: 0.9rem;">
                </div>
                <div style="margin-bottom: 0.75rem;">
                    <label
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.35rem;">Instructions
                        système</label>
                    <textarea id="personalityContentInput"
                        placeholder="Tu es un expert juridique. Réponds de façon précise et professionnelle..."
                        maxlength="3000" rows="5"
                        style="width: 100%; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 0.75rem; color: var(--text-primary); font-size: 0.9rem; font-family: inherit; resize: vertical;"></textarea>
                    <div style="font-size: 0.78rem; color: var(--text-muted); text-align: right; margin-top: 0.2rem;">
                        <span id="personalityCharCount">0</span> / 3000
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-cancel" id="btnCancelPersonalityForm">Annuler</button>
                    <button class="btn-confirm" id="btnSavePersonality">Sauvegarder</button>
                </div>
            </div>

            <!-- Bouton "Nouvelle personnalité" (caché si formulaire ouvert) -->
            <div id="btnNewPersonalityWrapper">
                <button class="btn-outline" id="btnNewPersonality"
                    style="width: 100%; margin-top: 0.5rem; padding: 0.5rem; font-size: 0.9rem; text-align: center; border: 1px dashed var(--border-color); background: none; color: var(--text-muted); border-radius: 8px; cursor: pointer;">
                    + Nouvelle personnalité
                </button>
            </div>

            <div class="modal-actions"
                style="border-top: 1px solid var(--border-color); margin-top: 1rem; padding-top: 1rem;">
                <button class="btn-cancel" id="btnClosePersonalityModal">Fermer</button>
            </div>
        </div>
    </div>
    <script>
        // ============================================
        // CONFIGURATION
        // ============================================
        const API_BASE = '/api';
        const AUTH_TOKEN = '{{ session('api_token') }}';

        // ============================================
        // ÉTAT DE L'APPLICATION
        // ============================================
        let currentConversationId = {{ isset($currentConversation) ? $currentConversation->id : 'null' }};
        let conversations = [];
        let isStreaming = false;
        let streamController = null;

        // ============================================
        // ÉLÉMENTS DOM
        // ============================================
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const btnSend = document.getElementById('btnSend');
        const btnStop = document.getElementById('btnStop');
        const btnNewChat = document.getElementById('btnNewChat');
        const conversationsList = document.getElementById('conversationsList');
        const sidebarSearch = document.getElementById('sidebarSearch');
        let searchQuery = '';
        const modelSelector = document.getElementById('modelSelector');
        const emptyChat = document.getElementById('emptyChat');
        const chatTitle = document.getElementById('chatTitle');
        const fileInput = document.getElementById('fileInput');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const removeImageBtn = document.getElementById('removeImageBtn');
        const documentChipContainer = document.getElementById('documentChipContainer');
        const documentChipName = document.getElementById('documentChipName');
        const documentChipMeta = document.getElementById('documentChipMeta');
        const removeDocumentBtn = document.getElementById('removeDocumentBtn');
        const btnAttach = document.getElementById('btnAttach');
        const btnImagine = document.getElementById('btnImagine');
        const imagineModal = document.getElementById('imagineModal');
        const imaginePrompt = document.getElementById('imaginePrompt');
        const imagineSize = document.getElementById('imagineSize');
        const imagineQuality = document.getElementById('imagineQuality');
        const imagineRemaining = document.getElementById('imagineRemaining');
        const btnCancelImagine = document.getElementById('btnCancelImagine');
        const btnConfirmImagine = document.getElementById('btnConfirmImagine');
        const imagineButtonText = document.getElementById('imagineButtonText');
        const foldersList = document.getElementById('foldersList');
        const btnAddFolder = document.getElementById('btnAddFolder');
        const folderModal = document.getElementById('folderModal');
        const folderNameInput = document.getElementById('folderNameInput');
        const btnCancelFolder = document.getElementById('btnCancelFolder');
        const btnConfirmFolder = document.getElementById('btnConfirmFolder');
        const moveModal = document.getElementById('moveModal');
        const moveFoldersList = document.getElementById('moveFoldersList');
        const btnCancelMove = document.getElementById('btnCancelMove');

        const renameConversationModal = document.getElementById('renameConversationModal');
        const renameConversationInput = document.getElementById('renameConversationInput');
        const btnCancelRenameConversation = document.getElementById('btnCancelRenameConversation');
        const btnConfirmRenameConversation = document.getElementById('btnConfirmRenameConversation');

        const renameFolderModal = document.getElementById('renameFolderModal');
        const renameFolderInput = document.getElementById('renameFolderInput');
        const btnCancelRenameFolder = document.getElementById('btnCancelRenameFolder');
        const btnConfirmRenameFolder = document.getElementById('btnConfirmRenameFolder');



        const btnTheme = document.getElementById('btnTheme');
        const btnGoogleCalendar = document.getElementById('btnGoogleCalendar');
        const btnMenuMobile = document.getElementById('btnMenuMobile');
        const btnMic = document.getElementById('btnMic');
        const btnActionsMenu = document.getElementById('btnActionsMenu');
        const actionsDropdown = document.getElementById('actionsDropdown');

        let selectedImageBase64 = null;
        let selectedDocument = null; // { filename, content, pages, chars, truncated }

        let folders = [];
        let conversationToMove = null;
        let conversationToRename = null;
        let folderToRename = null;

        // Personnalités IA
        let systemPrompts = [];
        let currentSystemPrompt = null; // { id, name, content } ou null (mode par défaut)

        // ============================================
        // INITIALISATION
        // ============================================
        document.addEventListener('DOMContentLoaded', async () => {
            await loadFolders();
            await loadConversations();

            // Si une conversation est spécifiée dans l'URL
            if (currentConversationId) {
                await loadConversation(currentConversationId);
            }

            checkGoogleCalendarStatus();
            initPushNotifications();
            await loadSystemPrompts();

            // Recherche dans la sidebar
            sidebarSearch.addEventListener('input', () => {
                searchQuery = sidebarSearch.value.trim().toLowerCase();
                renderConversationsList();
            });
        });

        // ============================================
        // GESTION DES CONVERSATIONS
        // ============================================

        // Charger la liste des conversations
        async function loadConversations() {
            try {
                const response = await fetch(`${API_BASE}/conversations`, {
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Erreur chargement conversations');

                conversations = await response.json();
                renderConversationsList();
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Afficher la liste des conversations (hors dossiers)
        function renderConversationsList() {
            conversationsList.innerHTML = '';

            // Filtrer les conversations sans dossier + recherche
            let unfolderedConversations = conversations.filter(c => !c.folder_id);
            if (searchQuery) {
                unfolderedConversations = unfolderedConversations.filter(c =>
                    (c.title || 'Nouvelle conversation').toLowerCase().includes(searchQuery)
                );
            }

            if (unfolderedConversations.length === 0 && !searchQuery) {
                conversationsList.innerHTML = `
            <div style="padding: 0.5rem 0.75rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                Aucune conversation
            </div>
        `;
            } else if (unfolderedConversations.length === 0 && searchQuery) {
                conversationsList.innerHTML = '';
            } else {
                unfolderedConversations.forEach(conv => {
                    const item = createConversationItem(conv);
                    conversationsList.appendChild(item);
                });
            }

            // Mettre à jour les dossiers aussi
            renderFoldersList();
        }

        // Charger une conversation spécifique
        async function loadConversation(conversationId) {
            try {
                // Charger les messages
                const response = await fetch(`${API_BASE}/conversations/${conversationId}/messages`, {
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Erreur chargement messages');

                const messages = await response.json();

                currentConversationId = conversationId;

                // Mettre à jour l'URL sans recharger
                history.pushState({}, '', `/chat/c/${conversationId}`);

                // Trouver le titre
                const conv = conversations.find(c => c.id === conversationId);
                chatTitle.textContent = conv?.title || 'Conversation';

                // Afficher les messages
                renderMessages(messages);

                // Mettre à jour la sidebar
                renderConversationsList();
                // Fermer la sidebar sur mobile
                handleMobileConversationSelect();

                // Afficher le bouton partage
                document.getElementById('btnShareConv').style.display = 'flex';

            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Afficher les messages
        function renderMessages(messages) {
            chatMessages.innerHTML = '';

            if (messages.length === 0) {
                chatMessages.appendChild(emptyChat);
                emptyChat.style.display = 'flex';
                return;
            }

            emptyChat.style.display = 'none';

            messages.forEach(msg => {
                appendMessage(msg.role, msg.content, false, msg.image_url || null, msg.id);
            });

            // Boutons copier (tous) + régénérer (dernier uniquement)
            const allAssistantMsgs = chatMessages.querySelectorAll('.message.assistant');
            allAssistantMsgs.forEach(msgDiv => addCopyButton(msgDiv));
            if (allAssistantMsgs.length > 0) {
                addRegenerateButton(allAssistantMsgs[allAssistantMsgs.length - 1]);
            }

            // Boutons copier + éditer sur les messages utilisateur
            chatMessages.querySelectorAll('.message.user').forEach(addCopyButton);
            chatMessages.querySelectorAll('.message.user[data-message-id]').forEach(addEditButton);

            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Créer une nouvelle conversation
        async function createConversation(firstMessage) {
            try {
                const response = await fetch(`${API_BASE}/conversations`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: firstMessage.substring(0, 50) + (firstMessage.length > 50 ? '...' : '')
                    })
                });

                if (!response.ok) throw new Error('Erreur création conversation');

                const conversation = await response.json();
                currentConversationId = conversation.id;

                // Mettre à jour l'URL
                history.pushState({}, '', `/chat/c/${conversation.id}`);

                // Recharger la liste
                await loadConversations();

                return conversation;

            } catch (error) {
                console.error('Erreur:', error);
                return null;
            }
        }

        // Supprimer une conversation
        async function deleteConversation(conversationId) {
            if (!confirm('Supprimer cette conversation ?')) return;

            try {
                const response = await fetch(`${API_BASE}/conversations/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Erreur suppression');

                // Si c'était la conversation active
                if (conversationId === currentConversationId) {
                    startNewChat();
                }

                await loadConversations();

            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Nouvelle conversation
        function startNewChat() {
            currentConversationId = null;
            chatTitle.textContent = 'Nouvelle conversation';
            history.pushState({}, '', '/chat');
            renderMessages([]);
            renderConversationsList();
            document.getElementById('btnShareConv').style.display = 'none';
        }

        // ============================================
        // ENVOI DE MESSAGES
        // ============================================

        async function sendMessage() {
            const message = chatInput.value.trim();
            if (!message && !selectedImageBase64 && !selectedDocument) return;
            if (isStreaming) return;

            // Arrêter la reconnaissance vocale si active
            if (isRecording && recognition) {
                isRecording = false;
                recognition.stop();
            }

            // Détecter /imagine
            if (message.toLowerCase().startsWith('/imagine ')) {
                imaginePrompt.value = message.substring(9);
                chatInput.value = '';
                generateImage();
                return;
            }

            // Masquer l'écran d'accueil
            emptyChat.style.display = 'none';

            // Afficher le message utilisateur (avec image si présente)
            appendMessage('user', message, false, selectedImageBase64);

            const imageToSend = selectedImageBase64;

            // Construire le message enrichi avec le contenu du document si présent
            let messageToSend = message;
            if (selectedDocument) {
                const docHeader = `[Document joint : ${selectedDocument.filename}${selectedDocument.pages ? ` — ${selectedDocument.pages} page(s)` : ''}]\n\n---\n${selectedDocument.content}\n---\n\n`;
                messageToSend = docHeader + (message || 'Analyse ce document et résume son contenu.');
            }

            chatInput.value = '';
            chatInput.style.height = 'auto';
            btnSend.style.display = 'none';
            btnMic.style.display = 'flex';
            clearImageSelection();
            clearDocumentSelection();

            // Créer une conversation si nécessaire
            let triggerTitleGeneration = false;
            if (!currentConversationId) {
                const conv = await createConversation(message || 'Analyse d\'image');
                if (!conv) {
                    appendMessage('assistant', 'Erreur lors de la création de la conversation.');
                    return;
                }
                chatTitle.textContent = conv.title;
                triggerTitleGeneration = true;
                document.getElementById('btnShareConv').style.display = 'flex';
            }

            // Envoyer avec streaming (messageToSend inclut le contenu du document si présent)
            await sendWithStreaming(messageToSend, imageToSend, triggerTitleGeneration);
        }
        // Envoi avec streaming
        async function sendWithStreaming(message, imageData = null, triggerTitleGeneration = false) {
            isStreaming = true;
            streamController = new AbortController();
            btnSend.style.display = 'none';
            btnMic.style.display = 'none';
            btnStop.style.display = 'flex';

            // Créer le message assistant avec animation de réflexion
            const assistantDiv = document.createElement('div');
            assistantDiv.className = 'message assistant';
            assistantDiv.innerHTML = `
        <div class="message-avatar" style="background: var(--bg-message-ai);">
            <lottie-player
                src="/animations/logo.json"
                background="transparent"
                speed="1"
                style="width: 36px; height: 36px;"
                loop autoplay>
            </lottie-player>
        </div>
        <div class="message-content">
            <div class="typing-indicator">
                <div class="dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
            </div>
        </div>
    `;
            chatMessages.appendChild(assistantDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            const contentDiv = assistantDiv.querySelector('.message-content');
            let fullContent = '';
            let firstChunk = true;

            try {
                const response = await fetch(`${API_BASE}/conversations/${currentConversationId}/chat-stream`, {
                    method: 'POST',
                    signal: streamController.signal,
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'text/event-stream',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        content: message,
                        model: modelSelector.value,
                        image: imageData,
                        system_prompt: currentSystemPrompt?.content ?? null
                    })
                });

                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            if (data === '[DONE]') continue;
                            try {
                                const parsed = JSON.parse(data);
                                if (parsed.chunk) {
                                    if (firstChunk) {
                                        if (!contentDiv.dataset.searchHtml) contentDiv.innerHTML = '';
                                        firstChunk = false;
                                    }
                                    contentDiv.querySelector('.synthesis-note')?.remove();
                                    fullContent += parsed.chunk;
                                    const prefix = contentDiv.dataset.searchHtml || '';
                                    contentDiv.innerHTML = prefix + formatMarkdown(fullContent);
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.clear_chunks) {
                                    fullContent = '';
                                    if (!contentDiv.dataset.searchHtml) { contentDiv.innerHTML = ''; firstChunk = true; }
                                }
                                if (parsed.searching_web) {
                                    if (firstChunk) { contentDiv.innerHTML = ''; firstChunk = false; }
                                    contentDiv.innerHTML = `<p class="searching-web-note">🔍 Recherche web en cours…</p>`;
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.search_results) {
                                    if (firstChunk) { contentDiv.innerHTML = ''; firstChunk = false; }
                                    contentDiv.innerHTML = renderSearchResults(parsed.search_results);
                                    contentDiv.innerHTML += `<p class="synthesis-note">✍️ Rédaction de la synthèse…</p>`;
                                    contentDiv.dataset.searchHtml = renderSearchResults(parsed.search_results);
                                    fullContent = '';
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.generating_image) {
                                    if (firstChunk) {
                                        contentDiv.innerHTML = '';
                                        firstChunk = false;
                                    }
                                    contentDiv.innerHTML = '<p class="generating-image-note">🎨 Génération de l\'image en cours…</p>';
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.image_url) {
                                    contentDiv.querySelector('.generating-image-note')?.remove();
                                    const img = document.createElement('img');
                                    img.src = parsed.image_url;
                                    img.className = 'generated-image';
                                    img.alt = parsed.image_prompt || 'Image générée';
                                    contentDiv.insertBefore(img, contentDiv.firstChild);
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.done && parsed.user_message?.id) {
                                    const userMsgs = chatMessages.querySelectorAll('.message.user');
                                    const lastUserMsg = userMsgs[userMsgs.length - 1];
                                    if (lastUserMsg && !lastUserMsg.dataset.messageId) {
                                        lastUserMsg.dataset.messageId = parsed.user_message.id;
                                        addCopyButton(lastUserMsg);
                                        addEditButton(lastUserMsg);
                                    }
                                }
                            } catch (e) { }
                        }
                    }
                }

                // Ajouter les boutons copier + régénérer
                addCopyButton(assistantDiv);
                addRegenerateButton(assistantDiv);

                // Générer un titre IA pour la première réponse
                if (triggerTitleGeneration) {
                    generateConversationTitle();
                }

                await loadConversations();

            } catch (error) {
                if (error.name === 'AbortError') {
                    if (firstChunk) {
                        assistantDiv.remove();
                    } else {
                        const stopNote = document.createElement('p');
                        stopNote.className = 'stop-note';
                        stopNote.textContent = '⏹ Génération arrêtée';
                        contentDiv.appendChild(stopNote);
                        addCopyButton(assistantDiv);
                        addRegenerateButton(assistantDiv);
                    }
                } else {
                    console.error('Erreur streaming:', error);
                    contentDiv.innerHTML = 'Erreur lors de la communication avec l\'IA. Veuillez réessayer.';
                }
            } finally {
                isStreaming = false;
                streamController = null;
                btnStop.style.display = 'none';
                btnSend.disabled = false;
                const hasText = chatInput.value.trim().length > 0;
                btnSend.style.display = hasText ? 'flex' : 'none';
                btnMic.style.display = hasText ? 'none' : 'flex';
            }
        }

        // ============================================
        // AFFICHAGE DES MESSAGES
        // ============================================

        function appendMessage(role, content, returnElement = false, imageData = null, msgId = null) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            if (msgId && role === 'user') {
                messageDiv.dataset.messageId = msgId;
            }

            let avatarHtml;
            if (role === 'user') {
                avatarHtml =
                    `<div class="message-avatar" style="background: var(--primary);">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>`;
            } else {
                avatarHtml = `<div class="message-avatar" style="background: var(--bg-message-ai);">
            <lottie-player 
                src="/animations/logo.json" 
                background="transparent" 
                speed="1"
                style="width: 36px; height: 36px;" 
                loop autoplay>
            </lottie-player>
        </div>`;
            }

            let imageHtml = '';
            if (imageData) {
                imageHtml = `<img src="${imageData}" class="message-image" alt="Image envoyée">`;
            }

            const contentHtml = role === 'assistant' ?
                formatMarkdown(content) :
                escapeHtml(content);

            messageDiv.innerHTML = `
        ${avatarHtml}
        <div class="message-content">
            ${imageHtml}
            ${contentHtml || ''}
        </div>
    `;

            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            if (returnElement) {
                return messageDiv;
            }
        }

        // ============================================
        // FORMATAGE MARKDOWN
        // ============================================

        // Configuration de marked
        marked.setOptions({
            highlight: function (code, lang) {
                if (lang && hljs.getLanguage(lang)) {
                    return hljs.highlight(code, {
                        language: lang
                    }).value;
                }
                return hljs.highlightAuto(code).value;
            },
            breaks: true,
            gfm: true
        });

        function renderSearchResults(data) {
            const { answer, sources } = data;
            let html = `<div class="search-results-block">`;
            html += `<div class="search-results-header">🔍 Sources web</div>`;

            if (answer) {
                html += `<div class="search-answer">`;
                html += `<div class="search-answer-label">💡 Réponse directe</div>`;
                html += `<p>${escapeHtml(answer)}</p>`;
                html += `</div>`;
            }

            if (sources && sources.length) {
                html += `<div class="search-sources-label">📚 Sources (${sources.length})</div>`;
                html += `<div class="search-sources">`;
                sources.forEach(s => {
                    html += `<div class="search-source-card">`;
                    html += `<a class="search-source-title" href="${escapeHtml(s.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(s.title)}</a>`;
                    html += `<div class="search-source-url">${escapeHtml(s.url)}</div>`;
                    html += `<p class="search-source-content">${escapeHtml(s.content)}</p>`;
                    html += `</div>`;
                });
                html += `</div>`;
            }

            html += `</div>`;
            return html;
        }

        function formatMarkdown(text) {
            if (!text) return '';

            // Parser avec marked
            let html = marked.parse(text);

            // Ajouter wrapper et bouton copier aux blocs de code
            html = html.replace(/<pre><code class="language-(\w+)">/g,
                '<div class="code-block-wrapper"><button class="copy-btn" onclick="copyCode(this)">Copier</button><pre><code class="language-$1">'
            );
            html = html.replace(/<pre><code>/g,
                '<div class="code-block-wrapper"><button class="copy-btn" onclick="copyCode(this)">Copier</button><pre><code>'
            );
            html = html.replace(/<\/code><\/pre>/g, '</code></pre></div>');

            return html;
        }

        // Copier le code
        function copyCode(button) {
            const codeBlock = button.parentElement.querySelector('code');
            const text = codeBlock.textContent;

            navigator.clipboard.writeText(text).then(() => {
                button.textContent = 'Copié !';
                setTimeout(() => {
                    button.textContent = 'Copier';
                }, 2000);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // ÉVÉNEMENTS
        // ============================================

        // Auto-resize du textarea + toggle send/mic
        chatInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
            const hasText = this.value.trim().length > 0;
            btnSend.style.display = hasText ? 'flex' : 'none';
            btnMic.style.display = hasText ? 'none' : 'flex';
        });

        // Envoi avec Entrée
        chatInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        btnSend.addEventListener('click', sendMessage);
        btnNewChat.addEventListener('click', startNewChat);

        // Suggestions
        document.querySelectorAll('.suggestion-card').forEach(card => {
            card.addEventListener('click', () => {
                chatInput.value = card.dataset.suggestion;
                chatInput.focus();
            });
        });

        // ============================================
        // GÉNÉRATION D'IMAGES
        // ============================================

        let isGeneratingImage = false;

        btnImagine.addEventListener('click', () => {
            imagineModal.classList.add('open');
            imaginePrompt.value = '';
            imaginePrompt.focus();
        });

        btnCancelImagine.addEventListener('click', () => {
            imagineModal.classList.remove('open');
        });

        imagineModal.addEventListener('click', (e) => {
            if (e.target === imagineModal) {
                imagineModal.classList.remove('open');
            }
        });

        btnConfirmImagine.addEventListener('click', () => {
            generateImage();
        });

        imaginePrompt.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                generateImage();
            } else if (e.key === 'Escape') {
                imagineModal.classList.remove('open');
            }
        });

        async function generateImage() {
            const prompt = imaginePrompt.value.trim();
            if (!prompt || isGeneratingImage) return;

            isGeneratingImage = true;
            imagineButtonText.textContent = 'Génération...';
            btnConfirmImagine.disabled = true;

            // Fermer le modal
            imagineModal.classList.remove('open');

            // Masquer l'écran d'accueil
            emptyChat.style.display = 'none';

            // Créer une conversation si nécessaire
            if (!currentConversationId) {
                const conv = await createConversation('Image: ' + prompt.substring(0, 40));
                if (!conv) {
                    appendMessage('assistant', 'Erreur lors de la création de la conversation.');
                    resetImagineButton();
                    return;
                }
                chatTitle.textContent = conv.title;
            }

            // Afficher le message utilisateur
            appendMessage('user', '/imagine ' + prompt);

            // Afficher le loader
            const loaderDiv = document.createElement('div');
            loaderDiv.className = 'message assistant';
            loaderDiv.innerHTML = `
        <div class="message-avatar" style="background: var(--bg-message-ai);">
            <lottie-player 
                src="/animations/logo.json" 
                background="transparent" 
                speed="1"
                style="width: 36px; height: 36px;" 
                loop autoplay>
            </lottie-player>
        </div>
        <div class="message-content">
            <div class="generating-loader">
                <div class="spinner"></div>
                <span>Génération de l'image en cours...</span>
            </div>
        </div>
    `;
            chatMessages.appendChild(loaderDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                const response = await fetch(`${API_BASE}/conversations/${currentConversationId}/imagine`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        size: imagineSize.value,
                        quality: imagineQuality.value
                    })
                });

                // Supprimer le loader
                loaderDiv.remove();

                if (!response.ok) {
                    const error = await response.json();
                    if (response.status === 429) {
                        appendMessage('assistant', `⚠️ ${error.message}`);
                    } else {
                        throw new Error(error.message || 'Erreur de génération');
                    }
                    resetImagineButton();
                    return;
                }

                const data = await response.json();

                // Afficher l'image générée
                const imageHtml = `
            <p>🎨 <strong>Image générée</strong></p>
            <a href="${data.image_url}" target="_blank">
                <img src="${data.image_url}" class="generated-image" alt="Image générée">
            </a>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">
                <em>Prompt : ${escapeHtml(data.revised_prompt || prompt)}</em>
            </p>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                📊 Images restantes aujourd'hui : ${data.remaining}/${data.limit}
            </p>
        `;

                const messageDiv = document.createElement('div');
                messageDiv.className = 'message assistant';
                messageDiv.innerHTML = `
            <div class="message-avatar" style="background: var(--bg-message-ai);">
                <lottie-player 
                    src="/animations/logo.json" 
                    background="transparent" 
                    speed="1"
                    style="width: 36px; height: 36px;" 
                    loop autoplay>
                </lottie-player>
            </div>
            <div class="message-content">${imageHtml}</div>
        `;
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                // Recharger les conversations
                await loadConversations();

            } catch (error) {
                console.error('Erreur génération:', error);
                loaderDiv.remove();
                appendMessage('assistant', '❌ Erreur lors de la génération de l\'image. Veuillez réessayer.');
            }

            resetImagineButton();
        }

        function resetImagineButton() {
            isGeneratingImage = false;
            imagineButtonText.textContent = 'Générer';
            btnConfirmImagine.disabled = false;
        }
        // ============================================
        // GESTION DES DOSSIERS
        // ============================================

        // Charger les dossiers
        async function loadFolders() {
            try {
                const response = await fetch(`${API_BASE}/folders`, {
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Erreur chargement dossiers');

                folders = await response.json();
                renderFoldersList();
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Afficher les dossiers
        function renderFoldersList() {
            foldersList.innerHTML = '';

            if (folders.length === 0) {
                foldersList.innerHTML = `
            <div style="padding: 0.5rem 0.75rem; color: var(--text-muted); font-size: 0.8rem;">
                Aucun dossier
            </div>
        `;
                return;
            }

            folders.forEach(folder => {
                let folderConversations = conversations.filter(c => c.folder_id === folder.id);
                if (searchQuery) {
                    folderConversations = folderConversations.filter(c =>
                        (c.title || 'Nouvelle conversation').toLowerCase().includes(searchQuery)
                    );
                    // Masquer le dossier s'il n'a aucune correspondance
                    if (folderConversations.length === 0) return;
                }

                const folderDiv = document.createElement('div');
                folderDiv.className = 'folder-item';
                folderDiv.dataset.folderId = folder.id;

                folderDiv.innerHTML = `
                    <div class="folder-header" draggable="false">
                        <span class="folder-icon">▶</span>
                        <span class="folder-name">${escapeHtml(folder.name)}</span>
                        <span class="folder-count">${folderConversations.length}</span>
                        <button class="edit-folder-btn" title="Renommer">✏️</button>
                        <button class="delete-folder-btn" title="Supprimer">🗑️</button>
                    </div>
                    <div class="folder-conversations"></div>
                `;

                const header = folderDiv.querySelector('.folder-header');
                const convContainer = folderDiv.querySelector('.folder-conversations');
                const deleteBtn = folderDiv.querySelector('.delete-folder-btn');

                // Toggle dossier
                header.addEventListener('click', (e) => {
                    if (e.target === deleteBtn) return;
                    folderDiv.classList.toggle('open');
                    header.classList.toggle('open');
                });

                // Supprimer dossier
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    deleteFolder(folder.id);
                });

                // Renommer dossier
                const editFolderBtn = folderDiv.querySelector('.edit-folder-btn');
                editFolderBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openRenameFolderModal(folder.id, folder.name);
                });

                // Drag & drop
                header.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    header.classList.add('drag-over');
                });

                header.addEventListener('dragleave', () => {
                    header.classList.remove('drag-over');
                });

                header.addEventListener('drop', (e) => {
                    e.preventDefault();
                    header.classList.remove('drag-over');
                    const conversationId = e.dataTransfer.getData('conversationId');
                    if (conversationId) {
                        moveConversationToFolder(parseInt(conversationId), folder.id);
                    }
                });

                // Afficher les conversations du dossier
                folderConversations.forEach(conv => {
                    const convItem = createConversationItem(conv);
                    convContainer.appendChild(convItem);
                });

                // Ouvrir le dossier automatiquement si une recherche est active
                if (searchQuery) {
                    folderDiv.classList.add('open');
                    header.classList.add('open');
                }

                foldersList.appendChild(folderDiv);
            });
        }

        // Créer un élément conversation (réutilisable)
        // Créer un élément conversation (réutilisable)
        function createConversationItem(conv) {
            const item = document.createElement('div');
            item.className = `conversation-item ${conv.id === currentConversationId ? 'active' : ''}`;
            item.draggable = true;
            item.dataset.conversationId = conv.id;

            item.innerHTML = `
        <span class="icon">💬</span>
        <span class="title">${escapeHtml(conv.title || 'Nouvelle conversation')}</span>
        <button class="edit-btn" title="Renommer">✏️</button>
        <button class="move-btn" title="Déplacer">📁</button>
        <button class="delete-btn" title="Supprimer">🗑️</button>
    `;

            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('delete-btn') &&
                    !e.target.classList.contains('move-btn') &&
                    !e.target.classList.contains('edit-btn')) {
                    loadConversation(conv.id);
                }
            });

            item.querySelector('.delete-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                deleteConversation(conv.id);
            });

            item.querySelector('.move-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                openMoveModal(conv.id);
            });

            item.querySelector('.edit-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                openRenameConversationModal(conv.id, conv.title);
            });

            // Drag events (desktop)
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('conversationId', conv.id);
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });

            return item;
        }

        // Créer un dossier
        async function createFolder(name) {
            try {
                const response = await fetch(`${API_BASE}/folders`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name
                    })
                });

                if (!response.ok) throw new Error('Erreur création dossier');

                await loadFolders();
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Supprimer un dossier
        async function deleteFolder(folderId) {
            if (!confirm('Supprimer ce dossier ? Les conversations seront déplacées hors du dossier.')) return;

            try {
                const response = await fetch(`${API_BASE}/folders/${folderId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Erreur suppression');

                await loadFolders();
                await loadConversations();
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Déplacer une conversation dans un dossier
        async function moveConversationToFolder(conversationId, folderId) {
            try {
                const response = await fetch(`${API_BASE}/conversations/${conversationId}/move`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        folder_id: folderId
                    })
                });

                if (!response.ok) throw new Error('Erreur déplacement');

                await loadConversations();
                await loadFolders();

                // Fermer la sidebar sur mobile après déplacement
                handleMobileConversationSelect();

            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Modal dossier
        btnAddFolder.addEventListener('click', () => {
            folderModal.classList.add('open');
            folderNameInput.value = '';
            folderNameInput.focus();
        });

        btnCancelFolder.addEventListener('click', () => {
            folderModal.classList.remove('open');
        });

        btnConfirmFolder.addEventListener('click', () => {
            const name = folderNameInput.value.trim();
            if (name) {
                createFolder(name);
                folderModal.classList.remove('open');
            }
        });

        folderNameInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                btnConfirmFolder.click();
            } else if (e.key === 'Escape') {
                folderModal.classList.remove('open');
            }
        });

        folderModal.addEventListener('click', (e) => {
            if (e.target === folderModal) {
                folderModal.classList.remove('open');
            }
        });

        // ============================================
        // MODAL DÉPLACER CONVERSATION
        // ============================================

        function openMoveModal(conversationId) {
            conversationToMove = conversationId;

            // Générer la liste des dossiers
            let html = `
        <div class="move-folder-item" data-folder-id="null" style="padding: 0.75rem; border-radius: 8px; cursor: pointer; margin-bottom: 0.5rem; background: rgba(255,255,255,0.05);">
            📄 Aucun dossier (racine)
        </div>
    `;

            folders.forEach(folder => {
                html += `
            <div class="move-folder-item" data-folder-id="${folder.id}" style="padding: 0.75rem; border-radius: 8px; cursor: pointer; margin-bottom: 0.5rem; background: rgba(255,255,255,0.05);">
                📁 ${escapeHtml(folder.name)}
            </div>
        `;
            });

            moveFoldersList.innerHTML = html;

            // Ajouter les événements de clic
            document.querySelectorAll('.move-folder-item').forEach(item => {
                item.addEventListener('click', () => {
                    const folderId = item.dataset.folderId === 'null' ? null : parseInt(item.dataset
                        .folderId);
                    moveConversationToFolder(conversationToMove, folderId);
                    moveModal.classList.remove('open');
                    conversationToMove = null;
                });

                // Hover effect
                item.addEventListener('mouseenter', () => {
                    item.style.background = 'rgba(99, 102, 241, 0.3)';
                });
                item.addEventListener('mouseleave', () => {
                    item.style.background = 'rgba(255,255,255,0.05)';
                });
            });

            moveModal.classList.add('open');
        }

        btnCancelMove.addEventListener('click', () => {
            moveModal.classList.remove('open');
            conversationToMove = null;
        });

        moveModal.addEventListener('click', (e) => {
            if (e.target === moveModal) {
                moveModal.classList.remove('open');
                conversationToMove = null;
            }
        });
        // ============================================
        // RENOMMER CONVERSATION
        // ============================================

        function openRenameConversationModal(conversationId, currentTitle) {
            conversationToRename = conversationId;
            renameConversationInput.value = currentTitle || '';
            renameConversationModal.classList.add('open');
            renameConversationInput.focus();
            renameConversationInput.select();
        }

        btnCancelRenameConversation.addEventListener('click', () => {
            renameConversationModal.classList.remove('open');
            conversationToRename = null;
        });

        btnConfirmRenameConversation.addEventListener('click', () => {
            renameConversation();
        });

        renameConversationInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                renameConversation();
            } else if (e.key === 'Escape') {
                renameConversationModal.classList.remove('open');
                conversationToRename = null;
            }
        });

        renameConversationModal.addEventListener('click', (e) => {
            if (e.target === renameConversationModal) {
                renameConversationModal.classList.remove('open');
                conversationToRename = null;
            }
        });

        async function renameConversation() {
            const newTitle = renameConversationInput.value.trim();
            if (!newTitle || !conversationToRename) return;

            try {
                const response = await fetch(`${API_BASE}/conversations/${conversationToRename}`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: newTitle
                    })
                });

                if (!response.ok) throw new Error('Erreur renommage');

                // Mettre à jour le titre si c'est la conversation active
                if (conversationToRename === currentConversationId) {
                    chatTitle.textContent = newTitle;
                }

                renameConversationModal.classList.remove('open');
                conversationToRename = null;

                await loadConversations();

            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors du renommage de la conversation');
            }
        }

        // ============================================
        // RENOMMER DOSSIER
        // ============================================

        function openRenameFolderModal(folderId, currentName) {
            folderToRename = folderId;
            renameFolderInput.value = currentName || '';
            renameFolderModal.classList.add('open');
            renameFolderInput.focus();
            renameFolderInput.select();
        }

        btnCancelRenameFolder.addEventListener('click', () => {
            renameFolderModal.classList.remove('open');
            folderToRename = null;
        });

        btnConfirmRenameFolder.addEventListener('click', () => {
            renameFolder();
        });

        renameFolderInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                renameFolder();
            } else if (e.key === 'Escape') {
                renameFolderModal.classList.remove('open');
                folderToRename = null;
            }
        });

        renameFolderModal.addEventListener('click', (e) => {
            if (e.target === renameFolderModal) {
                renameFolderModal.classList.remove('open');
                folderToRename = null;
            }
        });

        async function renameFolder() {
            const newName = renameFolderInput.value.trim();
            if (!newName || !folderToRename) return;

            try {
                const response = await fetch(`${API_BASE}/folders/${folderToRename}`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: newName
                    })
                });

                if (!response.ok) throw new Error('Erreur renommage');

                renameFolderModal.classList.remove('open');
                folderToRename = null;

                await loadFolders();

            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors du renommage du dossier');
            }
        }
        // ============================================
        // GESTION DU THÈME
        // ============================================

        // Charger le thème sauvegardé
        const savedTheme = localStorage.getItem('cortex-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeButton(savedTheme);

        btnTheme.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('cortex-theme', newTheme);
            updateThemeButton(newTheme);
        });

        function updateThemeButton(theme) {
            btnTheme.textContent = theme === 'dark' ? '☀️' : '🌙';
            btnTheme.title = theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre';
        }
        // ============================================
        // NOTIFICATIONS PUSH
        // ============================================
        const btnPushNotif = document.getElementById('btnPushNotif');
        let pushSubscription = null;

        async function initPushNotifications() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                btnPushNotif.style.display = 'none';
                return;
            }

            btnPushNotif.addEventListener('click', () => {
                togglePushNotifications();
            });

            // Vérifier si déjà abonné (sans bloquer au démarrage)
            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                if (reg.pushManager) {
                    const existing = await reg.pushManager.getSubscription();
                    if (existing) {
                        pushSubscription = existing;
                        btnPushNotif.classList.add('active');
                        btnPushNotif.title = 'Notifications activées (cliquer pour désactiver)';
                    }
                }
            } catch (e) {
                console.warn('SW init:', e);
            }
        }

        async function togglePushNotifications() {
            if (pushSubscription) {
                // Désabonner
                try {
                    await pushSubscription.unsubscribe();
                    await fetch(`${API_BASE}/push/unsubscribe`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ endpoint: pushSubscription.endpoint }),
                    });
                } catch (e) { /* ignorer les erreurs de désabonnement */ }
                pushSubscription = null;
                btnPushNotif.classList.remove('active');
                btnPushNotif.title = 'Activer les notifications';
                return;
            }

            // Demander la permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            const iconEl = btnPushNotif.querySelector('.item-icon');
            const originalText = iconEl.textContent;
            iconEl.textContent = '⏳';
            btnPushNotif.disabled = true;

            try {
                // 1. Enregistrer / récupérer le Service Worker
                const reg = await navigator.serviceWorker.register('/sw.js');

                // Attendre qu'il soit actif (max 8s)
                const swReg = await Promise.race([
                    navigator.serviceWorker.ready,
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('Service Worker non disponible (timeout)')), 8000)
                    ),
                ]);

                // 2. Récupérer la clé VAPID
                const res = await fetch(`${API_BASE}/push/vapid-key`, {
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`Erreur VAPID key (${res.status})`);
                const { public_key } = await res.json();

                // 3. S'abonner au push
                const sub = await swReg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(public_key),
                });

                // 4. Envoyer la subscription au serveur
                const subJSON = sub.toJSON();
                const saveRes = await fetch(`${API_BASE}/push/subscribe`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        endpoint: subJSON.endpoint,
                        public_key: subJSON.keys.p256dh,
                        auth_token: subJSON.keys.auth,
                    }),
                });
                if (!saveRes.ok) throw new Error(`Erreur enregistrement (${saveRes.status})`);

                pushSubscription = sub;
                btnPushNotif.classList.add('active');
                btnPushNotif.title = 'Notifications activées (cliquer pour désactiver)';

            } catch (e) {
                console.error('Push error:', e);
                alert('Impossible d\'activer les notifications :\n' + e.message);
            } finally {
                iconEl.textContent = originalText;
                btnPushNotif.disabled = false;
            }
        }

        // Convertit une clé VAPID base64url en Uint8Array
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = atob(base64);
            return new Uint8Array([...rawData].map(c => c.charCodeAt(0)));
        }

        // ============================================
        // PWA — Installation
        // ============================================
        const btnInstallApp = document.getElementById('btnInstallApp');
        let deferredInstallPrompt = null;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredInstallPrompt = e;
            btnInstallApp.style.display = '';
        });

        btnInstallApp.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            const { outcome } = await deferredInstallPrompt.userChoice;
            if (outcome === 'accepted') {
                btnInstallApp.style.display = 'none';
            }
            deferredInstallPrompt = null;
        });

        // ============================================
        // GOOGLE CALENDAR
        // ============================================

        async function checkGoogleCalendarStatus() {
            try {
                const res = await fetch(`${API_BASE}/google/status`, {
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                });
                const data = await res.json();
                updateGoogleCalendarButton(data.connected);
            } catch (e) {
                updateGoogleCalendarButton(false);
            }
        }

        function updateGoogleCalendarButton(connected) {
            btnGoogleCalendar.classList.toggle('connected', connected);
            btnGoogleCalendar.title = connected
                ? 'Google Calendar connecté — cliquer pour déconnecter'
                : 'Connecter Google Calendar';
        }

        btnGoogleCalendar.addEventListener('click', async () => {
            const isConnected = btnGoogleCalendar.classList.contains('connected');

            if (isConnected) {
                if (!confirm('Déconnecter Google Calendar ?')) return;
                try {
                    await fetch(`${API_BASE}/google/disconnect`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                    });
                    updateGoogleCalendarButton(false);
                } catch (e) { }
            } else {
                // Ouvrir la fenêtre d'auth Google
                const popup = window.open(
                    `/auth/google?token=${AUTH_TOKEN}`,
                    'google_auth',
                    'width=600,height=700,left=200,top=100'
                );
                // Écouter la fermeture du popup pour rafraîchir le statut
                const timer = setInterval(() => {
                    if (popup && popup.closed) {
                        clearInterval(timer);
                        checkGoogleCalendarStatus();
                    }
                }, 800);
            }
        });

        // ============================================
        // RECHERCHE WEB
        // ============================================


        // ============================================
        // MENU DÉROULANT ACTIONS
        // ============================================

        function closeActionsDropdown() {
            actionsDropdown.classList.remove('open');
            btnActionsMenu.classList.remove('open');
        }

        btnActionsMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = actionsDropdown.classList.toggle('open');
            btnActionsMenu.classList.toggle('open', isOpen);
        });

        // Fermer le dropdown quand on clique sur un item
        actionsDropdown.addEventListener('click', () => {
            closeActionsDropdown();
        });

        // Fermer le dropdown en cliquant en dehors
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.input-actions')) {
                closeActionsDropdown();
            }
        });

        // ============================================
        // SPEECH-TO-TEXT (RECONNAISSANCE VOCALE)
        // ============================================

        let recognition = null;
        let isRecording = false;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.lang = 'fr-FR';
            recognition.interimResults = false;  // Désactiver les résultats intermédiaires
            recognition.continuous = false;       // Désactiver le mode continu
            recognition.maxAlternatives = 1;

            recognition.onstart = () => {
                isRecording = true;
                btnMic.classList.add('recording');
                btnMic.title = 'Arrêter la dictée';
            };

            recognition.onend = () => {
                // Redémarrer automatiquement si toujours en mode enregistrement
                if (isRecording) {
                    try {
                        recognition.start();
                    } catch (e) {
                        // Ignorer si déjà démarré
                    }
                } else {
                    btnMic.classList.remove('recording');
                    btnMic.title = 'Dictée vocale';
                }
            };

            recognition.onerror = (event) => {
                console.error('Erreur reconnaissance vocale:', event.error);

                if (event.error === 'no-speech') {
                    // Pas de parole détectée, on continue si toujours actif
                    return;
                }

                if (event.error === 'aborted') {
                    // Ignoré, géré par onend
                    return;
                }

                // Erreurs bloquantes
                isRecording = false;
                btnMic.classList.remove('recording');
                btnMic.title = 'Dictée vocale';

                if (event.error === 'not-allowed') {
                    alert('Veuillez autoriser l\'accès au microphone pour utiliser la dictée vocale.');
                }
            };

            recognition.onresult = (event) => {
                // Avec continuous = false, on reçoit un seul résultat final par session
                const transcript = event.results[0][0].transcript.trim();

                if (transcript) {
                    const sep = chatInput.value && !chatInput.value.endsWith(' ') ? ' ' : '';
                    chatInput.value += sep + transcript;
                    chatInput.style.height = 'auto';
                    chatInput.style.height = Math.min(chatInput.scrollHeight, 150) + 'px';

                    // Mettre à jour l'affichage du bouton send/mic
                    const hasText = chatInput.value.trim().length > 0;
                    btnSend.style.display = hasText ? 'flex' : 'none';
                    btnMic.style.display = hasText ? 'none' : 'flex';
                }
            };

            btnMic.addEventListener('click', () => {
                if (isRecording) {
                    isRecording = false;
                    recognition.stop();
                } else {
                    try {
                        recognition.start();
                    } catch (e) {
                        console.error('Erreur démarrage:', e);
                    }
                }
            });

        } else {
            btnMic.classList.add('disabled');
            btnMic.title = 'Dictée vocale non supportée par ce navigateur';
            btnMic.addEventListener('click', () => {
                alert('La dictée vocale n\'est pas supportée par votre navigateur. Essayez Chrome ou Edge.');
            });
        }
        // ============================================
        // GESTION DES FICHIERS (images + PDF)
        // ============================================

        btnAttach.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Vérifier la taille (max 20MB)
            if (file.size > 20 * 1024 * 1024) {
                alert('Le fichier est trop volumineux (max 20 Mo)');
                return;
            }

            // IMAGE — traitement local en base64
            if (file.type.startsWith('image/')) {
                clearDocumentSelection();
                const reader = new FileReader();
                reader.onload = (event) => {
                    selectedImageBase64 = event.target.result;
                    imagePreview.src = selectedImageBase64;
                    imagePreviewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
                return;
            }

            // PDF — extraction côté serveur
            if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                clearImageSelection();
                documentChipName.textContent = file.name;
                documentChipMeta.textContent = 'Extraction en cours…';
                documentChipContainer.style.display = 'block';

                try {
                    const formData = new FormData();
                    formData.append('file', file);

                    const res = await fetch(`${API_BASE}/documents/extract`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' },
                        body: formData,
                    });

                    if (!res.ok) {
                        const err = await res.json();
                        throw new Error(err.message || 'Erreur extraction');
                    }

                    const data = await res.json();
                    selectedDocument = data;

                    const pages = data.pages ? `${data.pages} page(s)` : '';
                    const chars = data.chars ? ` · ${Math.round(data.chars / 1000)}k car.` : '';
                    const trunc = data.truncated ? ' · tronqué' : '';
                    documentChipMeta.textContent = pages + chars + trunc;
                } catch (err) {
                    documentChipContainer.style.display = 'none';
                    selectedDocument = null;
                    alert('Erreur : ' + err.message);
                }
                return;
            }

            alert('Format non supporté. Utilisez une image (JPG, PNG, WEBP) ou un PDF.');
            fileInput.value = '';
        });

        removeImageBtn.addEventListener('click', () => clearImageSelection());
        removeDocumentBtn.addEventListener('click', () => clearDocumentSelection());

        function clearImageSelection() {
            selectedImageBase64 = null;
            fileInput.value = '';
            imagePreviewContainer.style.display = 'none';
            imagePreview.src = '';
        }

        function clearDocumentSelection() {
            selectedDocument = null;
            documentChipContainer.style.display = 'none';
            documentChipName.textContent = '';
            documentChipMeta.textContent = '';
        }
        // Mobile sidebar
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        btnMenuMobile.addEventListener('click', () => {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('open');
        });

        sidebarOverlay.addEventListener('click', () => {
            closeMobileSidebar();
        });

        function closeMobileSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
        }

        // Bouton croix dans le header de la sidebar
        const btnSidebarClose = document.getElementById('btnSidebarClose');
        if (btnSidebarClose) {
            btnSidebarClose.addEventListener('click', closeMobileSidebar);
        }

        // Fermer la sidebar mobile après sélection d'une conversation
        function handleMobileConversationSelect() {
            if (window.innerWidth <= 1024) {
                closeMobileSidebar();
            }
        }

        // ============================================
        // BOUTON STOP
        // ============================================

        btnStop.addEventListener('click', () => {
            if (streamController) {
                streamController.abort();
            }
        });

        // ============================================
        // BOUTON RÉGÉNÉRER
        // ============================================

        function addCopyButton(messageDiv) {
            // Ne pas ajouter si déjà présent
            if (messageDiv.querySelector('.btn-copy-message')) return;

            const contentDiv = messageDiv.querySelector('.message-content');
            const btn = document.createElement('button');
            btn.className = 'btn-copy-message';
            btn.title = 'Copier la réponse';
            btn.innerHTML = '⎘ Copier';
            btn.addEventListener('click', () => copyMessageText(contentDiv, btn));
            contentDiv.appendChild(btn);
        }

        async function copyMessageText(contentDiv, btn) {
            // Cloner le nœud et supprimer les boutons du clone pour ne copier que le texte
            const clone = contentDiv.cloneNode(true);
            clone.querySelectorAll('.btn-copy-message, .btn-regenerate, .stop-note, .btn-edit-message').forEach(el => el.remove());
            const text = clone.innerText.trim();

            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                // Fallback pour les navigateurs sans Clipboard API
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            const orig = btn.innerHTML;
            btn.innerHTML = '✓ Copié !';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }

        function addRegenerateButton(messageDiv) {
            // Supprimer les boutons existants pour n'en avoir qu'un seul
            document.querySelectorAll('.btn-regenerate').forEach(btn => btn.remove());

            const btn = document.createElement('button');
            btn.className = 'btn-regenerate';
            btn.title = 'Régénérer cette réponse';
            btn.innerHTML = '↺ Régénérer';
            btn.addEventListener('click', regenerateLastResponse);
            messageDiv.querySelector('.message-content').appendChild(btn);
        }

        // ============================================
        // ÉDITION DE MESSAGES UTILISATEUR
        // ============================================

        function addEditButton(msgDiv) {
            if (msgDiv.querySelector('.btn-edit-message')) return;
            const btn = document.createElement('button');
            btn.className = 'btn-edit-message';
            btn.title = 'Modifier ce message';
            btn.innerHTML = '✏️';
            btn.addEventListener('click', () => enterEditMode(msgDiv));
            msgDiv.querySelector('.message-content').appendChild(btn);
        }

        function enterEditMode(msgDiv) {
            if (isStreaming) return;
            const contentDiv = msgDiv.querySelector('.message-content');
            const currentText = contentDiv.innerText.replace(/✏️$/, '').trim();

            contentDiv.innerHTML = `
                <textarea class="edit-textarea">${escapeHtml(currentText)}</textarea>
                <div class="edit-actions">
                    <button class="btn-cancel-edit">Annuler</button>
                    <button class="btn-confirm-edit btn-confirm">Modifier &amp; regénérer</button>
                </div>
            `;

            const ta = contentDiv.querySelector('.edit-textarea');
            ta.focus();
            ta.setSelectionRange(ta.value.length, ta.value.length);
            ta.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            ta.dispatchEvent(new Event('input'));

            contentDiv.querySelector('.btn-cancel-edit').addEventListener('click', () => {
                contentDiv.innerHTML = escapeHtml(currentText);
                addCopyButton(msgDiv);
                addEditButton(msgDiv);
            });

            contentDiv.querySelector('.btn-confirm-edit').addEventListener('click', () => {
                confirmEdit(msgDiv, ta);
            });

            ta.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    confirmEdit(msgDiv, ta);
                }
            });
        }

        async function confirmEdit(msgDiv, textarea) {
            const newContent = textarea.value.trim();
            if (!newContent || isStreaming) return;

            const msgId = msgDiv.dataset.messageId;

            try {
                const res = await fetch(`${API_BASE}/conversations/${currentConversationId}/messages/${msgId}`, {
                    method: 'PATCH',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ content: newContent })
                });

                if (!res.ok) throw new Error('Erreur édition');

                // Mettre à jour le contenu affiché
                msgDiv.querySelector('.message-content').innerHTML = escapeHtml(newContent);
                addCopyButton(msgDiv);
                addEditButton(msgDiv);

                // Supprimer du DOM tous les messages après ce message
                let next = msgDiv.nextElementSibling;
                while (next) {
                    const toRemove = next;
                    next = next.nextElementSibling;
                    toRemove.remove();
                }

                // Regénérer la réponse
                await regenerateLastResponse();

            } catch (e) {
                console.error('Erreur édition message:', e);
            }
        }

        async function regenerateLastResponse() {
            if (isStreaming || !currentConversationId) return;

            // Supprimer le dernier message assistant du DOM
            const assistantMessages = chatMessages.querySelectorAll('.message.assistant');
            const lastAssistantDiv = assistantMessages[assistantMessages.length - 1];
            if (lastAssistantDiv) lastAssistantDiv.remove();

            // Créer un nouveau div assistant avec typing indicator
            const assistantDiv = document.createElement('div');
            assistantDiv.className = 'message assistant';
            assistantDiv.innerHTML = `
        <div class="message-avatar" style="background: var(--bg-message-ai);">
            <lottie-player
                src="/animations/logo.json"
                background="transparent"
                speed="1"
                style="width: 36px; height: 36px;"
                loop autoplay>
            </lottie-player>
        </div>
        <div class="message-content">
            <div class="typing-indicator">
                <div class="dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
            </div>
        </div>
    `;
            chatMessages.appendChild(assistantDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            const contentDiv = assistantDiv.querySelector('.message-content');

            isStreaming = true;
            streamController = new AbortController();
            btnSend.style.display = 'none';
            btnMic.style.display = 'none';
            btnStop.style.display = 'flex';

            let fullContent = '';
            let firstChunk = true;

            try {
                const response = await fetch(`${API_BASE}/conversations/${currentConversationId}/regenerate`, {
                    method: 'POST',
                    signal: streamController.signal,
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'text/event-stream',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        model: modelSelector.value,
                        system_prompt: currentSystemPrompt?.content ?? null
                    })
                });

                if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value);
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            try {
                                const parsed = JSON.parse(data);
                                if (parsed.chunk) {
                                    if (firstChunk) {
                                        if (!contentDiv.dataset.searchHtml) contentDiv.innerHTML = '';
                                        firstChunk = false;
                                    }
                                    contentDiv.querySelector('.synthesis-note')?.remove();
                                    fullContent += parsed.chunk;
                                    const prefix = contentDiv.dataset.searchHtml || '';
                                    contentDiv.innerHTML = prefix + formatMarkdown(fullContent);
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.clear_chunks) {
                                    fullContent = '';
                                    if (!contentDiv.dataset.searchHtml) { contentDiv.innerHTML = ''; firstChunk = true; }
                                }
                                if (parsed.searching_web) {
                                    if (firstChunk) { contentDiv.innerHTML = ''; firstChunk = false; }
                                    contentDiv.innerHTML = `<p class="searching-web-note">🔍 Recherche web en cours…</p>`;
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.search_results) {
                                    if (firstChunk) { contentDiv.innerHTML = ''; firstChunk = false; }
                                    contentDiv.innerHTML = renderSearchResults(parsed.search_results);
                                    contentDiv.innerHTML += `<p class="synthesis-note">✍️ Rédaction de la synthèse…</p>`;
                                    contentDiv.dataset.searchHtml = renderSearchResults(parsed.search_results);
                                    fullContent = '';
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.generating_image) {
                                    if (firstChunk) {
                                        contentDiv.innerHTML = '';
                                        firstChunk = false;
                                    }
                                    contentDiv.innerHTML = '<p class="generating-image-note">🎨 Génération de l\'image en cours…</p>';
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                                if (parsed.image_url) {
                                    contentDiv.querySelector('.generating-image-note')?.remove();
                                    const img = document.createElement('img');
                                    img.src = parsed.image_url;
                                    img.className = 'generated-image';
                                    img.alt = parsed.image_prompt || 'Image générée';
                                    contentDiv.insertBefore(img, contentDiv.firstChild);
                                    chatMessages.scrollTop = chatMessages.scrollHeight;
                                }
                            } catch (e) { }
                        }
                    }
                }

                addCopyButton(assistantDiv);
                addRegenerateButton(assistantDiv);

            } catch (error) {
                if (error.name === 'AbortError') {
                    if (firstChunk) {
                        assistantDiv.remove();
                    } else {
                        const stopNote = document.createElement('p');
                        stopNote.className = 'stop-note';
                        stopNote.textContent = '⏹ Génération arrêtée';
                        contentDiv.appendChild(stopNote);
                        addCopyButton(assistantDiv);
                        addRegenerateButton(assistantDiv);
                    }
                } else {
                    contentDiv.innerHTML = 'Erreur lors de la régénération. Veuillez réessayer.';
                }
            } finally {
                isStreaming = false;
                streamController = null;
                btnStop.style.display = 'none';
                btnSend.disabled = false;
                const hasText = chatInput.value.trim().length > 0;
                btnSend.style.display = hasText ? 'flex' : 'none';
                btnMic.style.display = hasText ? 'none' : 'flex';
            }
        }

        // ============================================
        // TITRE IA
        // ============================================

        async function generateConversationTitle() {
            try {
                const response = await fetch(`${API_BASE}/conversations/${currentConversationId}/generate-title`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    chatTitle.textContent = data.title;
                    await loadConversations();
                }
            } catch (error) {
                console.error('Erreur génération titre:', error);
            }
        }

        // ============================================
        // PARTAGE DE CONVERSATION
        // ============================================

        const btnShareConv = document.getElementById('btnShareConv');
        const shareModal = document.getElementById('shareModal');
        const btnCancelShare = document.getElementById('btnCancelShare');
        const btnCreateShare = document.getElementById('btnCreateShare');
        const btnRevokeShare = document.getElementById('btnRevokeShare');
        const btnCopyShareUrl = document.getElementById('btnCopyShareUrl');
        const shareUrlInput = document.getElementById('shareUrlInput');
        const shareUrlGroup = document.getElementById('shareUrlGroup');

        let currentShareToken = null;

        btnShareConv.addEventListener('click', () => openShareModal());
        btnCancelShare.addEventListener('click', () => shareModal.classList.remove('open'));
        shareModal.addEventListener('click', (e) => { if (e.target === shareModal) shareModal.classList.remove('open'); });

        btnCopyShareUrl.addEventListener('click', () => {
            navigator.clipboard.writeText(shareUrlInput.value).then(() => {
                btnCopyShareUrl.textContent = '✓ Copié !';
                setTimeout(() => { btnCopyShareUrl.textContent = 'Copier'; }, 2000);
            });
        });

        btnCreateShare.addEventListener('click', async () => {
            if (!currentConversationId) return;
            try {
                const res = await fetch(`${API_BASE}/conversations/${currentConversationId}/share`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error();
                const data = await res.json();
                currentShareToken = data.token;
                shareUrlInput.value = data.url;
                shareUrlGroup.style.display = 'block';
                btnCreateShare.style.display = 'none';
                btnRevokeShare.style.display = 'inline-block';
            } catch (e) {
                console.error('Erreur partage:', e);
            }
        });

        btnRevokeShare.addEventListener('click', async () => {
            if (!currentConversationId) return;
            try {
                await fetch(`${API_BASE}/conversations/${currentConversationId}/share`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                });
                currentShareToken = null;
                shareUrlInput.value = '';
                shareUrlGroup.style.display = 'none';
                btnCreateShare.style.display = 'inline-block';
                btnRevokeShare.style.display = 'none';
            } catch (e) {
                console.error('Erreur révocation:', e);
            }
        });

        function openShareModal() {
            if (!currentConversationId) return;
            // Réinitialiser le modal
            shareUrlGroup.style.display = 'none';
            shareUrlInput.value = '';
            btnCreateShare.style.display = 'inline-block';
            btnRevokeShare.style.display = 'none';
            currentShareToken = null;
            shareModal.classList.add('open');
        }

        // ============================================
        // PERSONNALITÉS IA (SYSTEM PROMPTS)
        // ============================================

        const btnPersonality = document.getElementById('btnPersonality');
        const personalityModal = document.getElementById('personalityModal');
        const personalityList = document.getElementById('personalityList');
        const personalityForm = document.getElementById('personalityForm');
        const personalityEditId = document.getElementById('personalityEditId');
        const personalityNameInput = document.getElementById('personalityNameInput');
        const personalityContentInput = document.getElementById('personalityContentInput');
        const personalityCharCount = document.getElementById('personalityCharCount');
        const btnNewPersonality = document.getElementById('btnNewPersonality');
        const btnNewPersonalityWrapper = document.getElementById('btnNewPersonalityWrapper');
        const btnCancelPersonalityForm = document.getElementById('btnCancelPersonalityForm');
        const btnSavePersonality = document.getElementById('btnSavePersonality');
        const btnClosePersonalityModal = document.getElementById('btnClosePersonalityModal');

        btnPersonality.addEventListener('click', () => {
            renderPersonalityList();
            hidePersonalityForm();
            personalityModal.classList.add('open');
        });
        btnClosePersonalityModal.addEventListener('click', () => personalityModal.classList.remove('open'));
        personalityModal.addEventListener('click', (e) => { if (e.target === personalityModal) personalityModal.classList.remove('open'); });

        btnNewPersonality.addEventListener('click', () => showPersonalityForm());
        btnCancelPersonalityForm.addEventListener('click', () => hidePersonalityForm());

        personalityContentInput.addEventListener('input', function () {
            personalityCharCount.textContent = this.value.length;
        });

        btnSavePersonality.addEventListener('click', async () => {
            const name = personalityNameInput.value.trim();
            const content = personalityContentInput.value.trim();
            const editId = personalityEditId.value || null;
            if (!name || !content) return;
            await savePersonality(name, content, editId ? parseInt(editId) : null);
        });

        async function loadSystemPrompts() {
            try {
                const res = await fetch(`${API_BASE}/system-prompts`, {
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                systemPrompts = await res.json();
                // Appliquer le prompt par défaut s'il existe
                const defaultPrompt = systemPrompts.find(p => p.is_default);
                if (defaultPrompt) {
                    currentSystemPrompt = defaultPrompt;
                    updatePersonalityButton();
                }
            } catch (e) {
                console.error('Erreur chargement personnalités:', e);
            }
        }

        function renderPersonalityList() {
            let html = '';

            // Option "Par défaut"
            const isDefault = currentSystemPrompt === null;
            html += `<div class="personality-item ${isDefault ? 'active' : ''}" onclick="selectPersonality(null)">
                <div class="personality-item-name">
                    <span>⭐ Par défaut</span>
                    ${isDefault ? '<span class="personality-active-badge">Actif</span>' : ''}
                </div>
                <div class="personality-item-desc">Comportement standard de l'IA</div>
            </div>`;

            systemPrompts.forEach(p => {
                const isActive = currentSystemPrompt?.id === p.id;
                html += `<div class="personality-item ${isActive ? 'active' : ''}" onclick="selectPersonality(${p.id})">
                    <div class="personality-item-name">
                        <span>${escapeHtml(p.name)}</span>
                        <div style="display:flex;gap:0.4rem;align-items:center;">
                            ${isActive ? '<span class="personality-active-badge">Actif</span>' : ''}
                            ${p.is_default ? '<span class="personality-default-badge">Défaut</span>' : ''}
                            <button class="personality-btn-icon" title="Modifier" onclick="event.stopPropagation(); editPersonality(${p.id})">✏️</button>
                            <button class="personality-btn-icon" title="Définir comme défaut" onclick="event.stopPropagation(); setDefaultPersonality(${p.id})">${p.is_default ? '⭐' : '☆'}</button>
                            <button class="personality-btn-icon danger" title="Supprimer" onclick="event.stopPropagation(); deletePersonality(${p.id})">🗑</button>
                        </div>
                    </div>
                    <div class="personality-item-desc">${escapeHtml(p.content.substring(0, 80))}${p.content.length > 80 ? '…' : ''}</div>
                </div>`;
            });

            personalityList.innerHTML = html;
        }

        function selectPersonality(idOrNull) {
            if (idOrNull === null) {
                currentSystemPrompt = null;
            } else {
                currentSystemPrompt = systemPrompts.find(p => p.id === idOrNull) || null;
            }
            updatePersonalityButton();
            renderPersonalityList();
            personalityModal.classList.remove('open');
        }

        function updatePersonalityButton() {
            if (currentSystemPrompt) {
                btnPersonality.classList.add('active');
                btnPersonality.title = `Personnalité : ${currentSystemPrompt.name}`;
            } else {
                btnPersonality.classList.remove('active');
                btnPersonality.title = 'Personnalité IA';
            }
        }

        function showPersonalityForm(prompt = null) {
            personalityEditId.value = prompt ? prompt.id : '';
            personalityNameInput.value = prompt ? prompt.name : '';
            personalityContentInput.value = prompt ? prompt.content : '';
            personalityCharCount.textContent = (prompt ? prompt.content : '').length;
            personalityForm.style.display = 'block';
            btnNewPersonalityWrapper.style.display = 'none';
            personalityNameInput.focus();
        }

        function hidePersonalityForm() {
            personalityForm.style.display = 'none';
            btnNewPersonalityWrapper.style.display = 'block';
        }

        function editPersonality(id) {
            const prompt = systemPrompts.find(p => p.id === id);
            if (prompt) showPersonalityForm(prompt);
        }

        async function savePersonality(name, content, id = null) {
            const url = id ? `${API_BASE}/system-prompts/${id}` : `${API_BASE}/system-prompts`;
            const method = id ? 'PUT' : 'POST';
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Authorization': `Bearer ${AUTH_TOKEN}`,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ name, content })
                });
                if (!res.ok) {
                    const err = await res.json();
                    alert(err.message || 'Erreur lors de la sauvegarde');
                    return;
                }
                const saved = await res.json();
                if (id) {
                    systemPrompts = systemPrompts.map(p => p.id === id ? saved : p);
                    if (currentSystemPrompt?.id === id) currentSystemPrompt = saved;
                } else {
                    systemPrompts.push(saved);
                }
                hidePersonalityForm();
                renderPersonalityList();
                updatePersonalityButton();
            } catch (e) {
                console.error('Erreur sauvegarde personnalité:', e);
            }
        }

        async function deletePersonality(id) {
            if (!confirm('Supprimer cette personnalité ?')) return;
            try {
                await fetch(`${API_BASE}/system-prompts/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}` }
                });
                systemPrompts = systemPrompts.filter(p => p.id !== id);
                if (currentSystemPrompt?.id === id) {
                    currentSystemPrompt = null;
                    updatePersonalityButton();
                }
                renderPersonalityList();
            } catch (e) {
                console.error('Erreur suppression personnalité:', e);
            }
        }

        async function setDefaultPersonality(id) {
            try {
                const res = await fetch(`${API_BASE}/system-prompts/${id}/set-default`, {
                    method: 'PATCH',
                    headers: { 'Authorization': `Bearer ${AUTH_TOKEN}`, 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const updated = await res.json();
                systemPrompts = systemPrompts.map(p => ({ ...p, is_default: p.id === id }));
                if (currentSystemPrompt?.id === id) currentSystemPrompt = updated;
                renderPersonalityList();
            } catch (e) {
                console.error('Erreur set défaut:', e);
            }
        }

        // Exposer les fonctions appelées via onclick dans innerHTML
        window.selectPersonality = selectPersonality;
        window.editPersonality = editPersonality;
        window.deletePersonality = deletePersonality;
        window.setDefaultPersonality = setDefaultPersonality;

        console.log('HR Chatbot Web initialisé');
        console.log('Token présent:', AUTH_TOKEN ? 'Oui' : 'Non');
    </script>
</body>

</html>