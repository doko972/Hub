@extends('layouts.app')

@section('title', 'Générateur de QR Code')
@section('page-title', 'Outils')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-header__title">Générateur de QR Code</h1>
        <p class="page-header__subtitle">Générez des QR codes pour vos configurations SIP, contacts et bien plus encore</p>
    </div>
</div>

<div class="qr-tool">

    {{-- Onglets principaux --}}
    <div class="qr-tabs">
        <button class="qr-tab is-active" data-tab="sip">📞 Configuration SIP</button>
        <button class="qr-tab" data-tab="favoris">⭐ Favoris</button>
        <button class="qr-tab" data-tab="qr">🔲 QR Code libre</button>
    </div>

    {{-- Colonne gauche : formulaires --}}
    <div class="qr-inputs">

        {{-- Panneau SIP --}}
        <div class="qr-panel is-active" id="qr-panel-sip">
            <div class="qr-grid-2">
                <div class="qr-field">
                    <label class="qr-label" for="sip-username">Identifiant (username)</label>
                    <input class="qr-input" id="sip-username" type="text" placeholder="1002">
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="sip-password">Mot de passe</label>
                    <div class="qr-pw-wrap">
                        <input class="qr-input" id="sip-password" type="password" placeholder="motdepasse">
                        <button type="button" class="qr-pw-toggle" id="qr-toggle-pw" title="Afficher / masquer">👁</button>
                    </div>
                </div>
            </div>
            <div class="qr-field">
                <label class="qr-label" for="sip-domain">Domaine / IP du serveur</label>
                <input class="qr-input" id="sip-domain" type="text" placeholder="51.91.145.39">
            </div>
            <div class="qr-field">
                <label class="qr-label" for="sip-display">Nom affiché (display)</label>
                <input class="qr-input" id="sip-display" type="text" placeholder="Jean Dupont">
            </div>
            <div class="qr-grid-2">
                <div class="qr-field">
                    <label class="qr-label" for="sip-transport">Transport</label>
                    <select class="qr-select" id="sip-transport">
                        <option value="udp">UDP</option>
                        <option value="tcp">TCP</option>
                        <option value="tls">TLS</option>
                    </select>
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="sip-port">Port</label>
                    <input class="qr-input" id="sip-port" type="number" min="1" max="65535" placeholder="5060">
                </div>
            </div>
            <div class="qr-field">
                    <label class="qr-label" for="sip-admin-password">Mot de passe admin</label>
                    <div class="qr-pw-wrap">
                        <input class="qr-input" id="sip-admin-password" type="password" placeholder="MonMotDePasseAdmin2024">
                        <button type="button" class="qr-pw-toggle" id="qr-toggle-admin-pw" title="Afficher / masquer">👁</button>
                    </div>
                </div>

            <button class="qr-btn-clear" id="qr-clear-sip">Vider les champs</button>
        </div>

        {{-- Panneau Favoris --}}
        <div class="qr-panel" id="qr-panel-favoris" style="display:none">
            <label class="qr-label">Contacts</label>
            <div id="qr-contacts-list"></div>
            <button class="qr-btn-add" id="qr-add-contact">＋ Ajouter un contact</button>
            <p class="qr-hint">BLF (Busy Lamp Field) affiche l'état de la ligne en temps réel sur le softphone.</p>
            <button class="qr-btn-clear" id="qr-clear-favoris">Vider les contacts</button>
        </div>

        {{-- Panneau QR Code libre --}}
        <div class="qr-panel" id="qr-panel-qr" style="display:none">
            <div class="qr-subtabs">
                <button class="qr-subtab is-active" data-subtab="url">🔗 URL</button>
                <button class="qr-subtab" data-subtab="text">💬 Texte</button>
                <button class="qr-subtab" data-subtab="contact">👤 Contact</button>
            </div>

            <div class="qr-subpanel is-active" id="qr-subpanel-url">
                <div class="qr-field">
                    <label class="qr-label" for="qr-url">URL du site</label>
                    <input class="qr-input" id="qr-url" type="text" placeholder="example.com ou https://example.com">
                    <p class="qr-hint">Le protocole https:// sera ajouté automatiquement si absent.</p>
                </div>
            </div>

            <div class="qr-subpanel" id="qr-subpanel-text" style="display:none">
                <div class="qr-field">
                    <label class="qr-label" for="qr-text">Contenu textuel</label>
                    <textarea class="qr-input qr-textarea" id="qr-text" placeholder="Saisissez n'importe quel texte…"></textarea>
                </div>
            </div>

            <div class="qr-subpanel" id="qr-subpanel-contact" style="display:none">
                <div class="qr-grid-2">
                    <div class="qr-field">
                        <label class="qr-label" for="qr-c-first">Prénom</label>
                        <input class="qr-input" id="qr-c-first" type="text" placeholder="Jean">
                    </div>
                    <div class="qr-field">
                        <label class="qr-label" for="qr-c-last">Nom</label>
                        <input class="qr-input" id="qr-c-last" type="text" placeholder="Dupont">
                    </div>
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="qr-c-phone">Téléphone</label>
                    <input class="qr-input" id="qr-c-phone" type="tel" placeholder="+33 6 12 34 56 78">
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="qr-c-email">Email</label>
                    <input class="qr-input" id="qr-c-email" type="email" placeholder="jean.dupont@exemple.fr">
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="qr-c-org">Organisation</label>
                    <input class="qr-input" id="qr-c-org" type="text" placeholder="Nom de l'entreprise">
                </div>
                <div class="qr-field">
                    <label class="qr-label" for="qr-c-url">Site web</label>
                    <input class="qr-input" id="qr-c-url" type="url" placeholder="https://exemple.fr">
                </div>
            </div>

            <button class="qr-btn-clear" id="qr-clear-free">Vider les champs</button>
        </div>

    </div>

    {{-- Colonne droite : aperçu QR code --}}
    <div class="qr-preview">

        <div class="qr-canvas-box">
            <div class="qr-placeholder" id="qr-placeholder">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>
                    <rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/>
                    <rect x="18" y="18" width="3" height="3"/>
                </svg>
                Remplissez le formulaire pour prévisualiser
            </div>
            <canvas class="qr-canvas" id="qr-canvas"></canvas>
        </div>

        <p class="qr-warning" id="qr-warning"></p>

        <div class="qr-actions" id="qr-actions" style="display:none">
            <button class="qr-btn-download" id="qr-btn-download">⬇ Télécharger le JSON</button>
            <button class="qr-btn-qr" id="qr-btn-qr">📱 Télécharger le QR code</button>
            <button class="qr-btn-copy" id="qr-btn-copy">📋 Copier le JSON</button>
        </div>

        <pre class="qr-data-box" id="qr-data-box" style="display:none"></pre>

    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
@endpush
