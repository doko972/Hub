import QRious from 'qrious';
import { showToast } from './toast.js';

export function initQrCode() {
    const canvas = document.getElementById('qr-canvas');
    if (!canvas) return;

    const placeholder   = document.getElementById('qr-placeholder');
    const actions       = document.getElementById('qr-actions');
    const dataBox       = document.getElementById('qr-data-box');
    const warningBox    = document.getElementById('qr-warning');
    const btnDownload   = document.getElementById('qr-btn-download');
    const btnDownloadQr = document.getElementById('qr-btn-qr');
    const btnCopy       = document.getElementById('qr-btn-copy');
    const contactsList  = document.getElementById('qr-contacts-list');

    let activeTab    = 'sip';
    let qrSubTab     = 'url';
    let qr           = null;
    let contactRowId = 0;

    // ── Tab switching ──
    document.querySelectorAll('.qr-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.qr-tab').forEach(t => t.classList.remove('is-active'));
            document.querySelectorAll('.qr-panel').forEach(p => {
                p.classList.remove('is-active');
                p.style.display = 'none';
            });
            tab.classList.add('is-active');
            activeTab = tab.dataset.tab;
            const activePanel = document.getElementById('qr-panel-' + activeTab);
            activePanel.classList.add('is-active');
            activePanel.style.display = 'block';
            updatePreview();
        });
    });

    // ── Sub-tab switching ──
    document.querySelectorAll('.qr-subtab').forEach(sub => {
        sub.addEventListener('click', () => {
            document.querySelectorAll('.qr-subtab').forEach(s => s.classList.remove('is-active'));
            document.querySelectorAll('.qr-subpanel').forEach(p => {
                p.classList.remove('is-active');
                p.style.display = 'none';
            });
            sub.classList.add('is-active');
            qrSubTab = sub.dataset.subtab;
            const activeSubpanel = document.getElementById('qr-subpanel-' + qrSubTab);
            activeSubpanel.classList.add('is-active');
            activeSubpanel.style.display = 'block';
            updatePreview();
        });
    });

    // ── SIP: affichage/masquage mot de passe ──
    document.getElementById('qr-toggle-pw').addEventListener('click', () => {
        const pw = document.getElementById('sip-password');
        pw.type  = pw.type === 'password' ? 'text' : 'password';
    });

    document.getElementById('qr-toggle-admin-pw').addEventListener('click', () => {
        const pw = document.getElementById('sip-admin-password');
        pw.type  = pw.type === 'password' ? 'text' : 'password';
    });

    // ── SIP: mise à jour en temps réel ──
    ['sip-username', 'sip-password', 'sip-domain', 'sip-display', 'sip-transport', 'sip-port', 'sip-admin-password'].forEach(id => {
        const el = document.getElementById(id);
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    document.getElementById('qr-clear-sip').addEventListener('click', () => {
        ['sip-username', 'sip-password', 'sip-domain', 'sip-display', 'sip-port', 'sip-admin-password'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('sip-transport').value = 'udp';
        updatePreview();
    });

    // ── Favoris: gestion des lignes de contacts ──
    function addContactRow(name = '', phone = '', blf = false) {
        const rowId = 'qr-c-' + (contactRowId++);
        const row   = document.createElement('div');
        row.className  = 'qr-contact-row';
        row.dataset.id = rowId;

        const nameInput       = document.createElement('input');
        nameInput.className   = 'qr-input';
        nameInput.type        = 'text';
        nameInput.placeholder = 'Nom (ex: Jean Dupont)';
        nameInput.value       = name;
        nameInput.dataset.field = 'name';

        const phoneInput       = document.createElement('input');
        phoneInput.className   = 'qr-input';
        phoneInput.type        = 'text';
        phoneInput.placeholder = 'Numéro (ex: 1002)';
        phoneInput.value       = phone;
        phoneInput.dataset.field = 'phone';

        const blfLabel = document.createElement('label');
        blfLabel.className = 'qr-blf-wrap';
        const blfInput = document.createElement('input');
        blfInput.type  = 'checkbox';
        blfInput.dataset.field = 'blf';
        blfInput.checked = blf;
        blfLabel.appendChild(blfInput);
        blfLabel.appendChild(document.createTextNode(' BLF'));

        const removeBtn       = document.createElement('button');
        removeBtn.type        = 'button';
        removeBtn.className   = 'qr-btn-remove';
        removeBtn.title       = 'Supprimer';
        removeBtn.textContent = '✕';

        row.append(nameInput, phoneInput, blfLabel, removeBtn);
        row.querySelectorAll('input').forEach(el => {
            el.addEventListener('input', updatePreview);
            el.addEventListener('change', updatePreview);
        });
        removeBtn.addEventListener('click', () => { row.remove(); updatePreview(); });
        contactsList.appendChild(row);
    }

    document.getElementById('qr-add-contact').addEventListener('click', () => {
        addContactRow();
        updatePreview();
    });

    document.getElementById('qr-clear-favoris').addEventListener('click', () => {
        contactsList.innerHTML = '';
        addContactRow();
        updatePreview();
    });

    addContactRow();
    addContactRow();

    // ── QR libre: mise à jour en temps réel ──
    document.querySelectorAll('#qr-panel-qr .qr-input, #qr-panel-qr .qr-textarea').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    document.getElementById('qr-clear-free').addEventListener('click', () => {
        document.querySelectorAll('#qr-panel-qr .qr-input, #qr-panel-qr .qr-textarea').forEach(el => { el.value = ''; });
        updatePreview();
    });

    // ── Configurations enregistrées ────────────────────────────────────────
    //
    // Le formulaire est entièrement côté client : sans cela, un rafraîchissement
    // faisait perdre toute la saisie. L'état part au serveur, où il est chiffré
    // (il contient les mots de passe SIP et administrateur).

    const SIP_FIELDS  = ['sip-username', 'sip-password', 'sip-domain', 'sip-display',
                         'sip-transport', 'sip-port', 'sip-admin-password'];
    const FREE_FIELDS = ['qr-url', 'qr-text', 'qr-c-first', 'qr-c-last',
                         'qr-c-phone', 'qr-c-email', 'qr-c-org', 'qr-c-url'];

    const presetsRoot = document.querySelector('[data-qr-presets]');

    function readField(id) {
        return document.getElementById(id)?.value ?? '';
    }

    /** État complet du formulaire, tel qu'il sera enregistré. */
    function collectState() {
        const sip  = {};
        const free = {};

        SIP_FIELDS.forEach(id => { sip[id] = readField(id); });
        FREE_FIELDS.forEach(id => { free[id] = readField(id); });

        const contacts = [];
        contactsList.querySelectorAll('.qr-contact-row').forEach(row => {
            contacts.push({
                name:  row.querySelector('[data-field="name"]').value,
                phone: row.querySelector('[data-field="phone"]').value,
                blf:   row.querySelector('[data-field="blf"]').checked,
            });
        });

        return { version: 1, tab: activeTab, subTab: qrSubTab, sip, free, contacts };
    }

    /** Rejoue un état enregistré dans le formulaire. */
    function applyState(state) {
        if (!state || typeof state !== 'object') return;

        Object.entries(state.sip ?? {}).forEach(([id, valeur]) => {
            const el = document.getElementById(id);
            if (el) el.value = valeur;
        });

        Object.entries(state.free ?? {}).forEach(([id, valeur]) => {
            const el = document.getElementById(id);
            if (el) el.value = valeur;
        });

        // Les lignes de contacts sont dynamiques : on les reconstruit.
        contactsList.innerHTML = '';
        (state.contacts ?? []).forEach(c => addContactRow(c.name ?? '', c.phone ?? '', Boolean(c.blf)));
        if (!contactsList.children.length) addContactRow();

        // Onglets : on rejoue le clic pour réutiliser la logique d'affichage.
        document.querySelector(`.qr-subtab[data-subtab="${state.subTab}"]`)?.click();
        document.querySelector(`.qr-tab[data-tab="${state.tab}"]`)?.click();

        updatePreview();
    }

    if (presetsRoot) {
        const list       = presetsRoot.querySelector('[data-preset-list]');
        const storeUrl   = presetsRoot.dataset.storeUrl;
        const showUrl    = presetsRoot.dataset.showUrl;
        const destroyUrl = presetsRoot.dataset.destroyUrl;

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        function addPresetToList(preset) {
            presetsRoot.querySelector('[data-preset-empty]')?.remove();

            // Déjà présente (réenregistrement sous le même nom) : rien à ajouter.
            if (list.querySelector(`[data-preset-id="${preset.id}"]`)) return;

            const item = document.createElement('li');
            item.className = 'qr-preset';
            item.dataset.presetId = preset.id;

            const charger = document.createElement('button');
            charger.type = 'button';
            charger.className = 'qr-preset__load';
            charger.dataset.presetLoad = '';
            charger.textContent = preset.name;

            const supprimer = document.createElement('button');
            supprimer.type = 'button';
            supprimer.className = 'qr-preset__delete';
            supprimer.dataset.presetDelete = '';
            supprimer.title = 'Supprimer';
            supprimer.setAttribute('aria-label', `Supprimer ${preset.name}`);
            supprimer.textContent = '×';

            item.append(charger, supprimer);
            list.appendChild(item);
        }

        presetsRoot.querySelector('[data-preset-save]').addEventListener('click', async () => {
            const nom = window.prompt('Nom de la configuration :', '');
            if (!nom || !nom.trim()) return;

            try {
                const reponse = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ name: nom.trim(), payload: collectState() }),
                });

                const donnees = await reponse.json();

                if (!reponse.ok) {
                    showToast(donnees.message ?? "L'enregistrement a échoué.", 'error');
                    return;
                }

                addPresetToList(donnees.preset);
                showToast(donnees.updated ? 'Configuration mise à jour.' : 'Configuration enregistrée.', 'success');
            } catch {
                showToast("L'enregistrement a échoué.", 'error');
            }
        });

        list.addEventListener('click', async (event) => {
            const item = event.target.closest('[data-preset-id]');
            if (!item) return;

            const id = item.dataset.presetId;

            if (event.target.closest('[data-preset-load]')) {
                try {
                    const reponse = await fetch(showUrl.replace('__ID__', id), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });

                    if (!reponse.ok) throw new Error();

                    const { name, payload } = await reponse.json();
                    applyState(payload);
                    showToast(`« ${name} » chargée.`, 'success');
                } catch {
                    showToast('Impossible de charger cette configuration.', 'error');
                }

                return;
            }

            if (event.target.closest('[data-preset-delete]')) {
                if (!window.confirm('Supprimer cette configuration ?')) return;

                try {
                    const reponse = await fetch(destroyUrl.replace('__ID__', id), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    });

                    if (!reponse.ok) throw new Error();

                    item.remove();
                    showToast('Configuration supprimée.', 'success');
                } catch {
                    showToast('La suppression a échoué.', 'error');
                }
            }
        });
    }

    // ── Construction des données ──
    function buildSipData() {
        const username       = document.getElementById('sip-username').value.trim();
        const password       = document.getElementById('sip-password').value;
        const domain         = document.getElementById('sip-domain').value.trim();
        const display        = document.getElementById('sip-display').value.trim();
        const transport      = document.getElementById('sip-transport').value;
        const portRaw        = document.getElementById('sip-port').value.trim();
        const admin_password = document.getElementById('sip-admin-password').value;

        if (!username && !password && !domain && !display && !portRaw && !admin_password) return null;

        const data = { username, password, domain, display, transport, port: portRaw ? parseInt(portRaw, 10) : 5060 };
        if (admin_password) data.admin_password = admin_password;
        return data;
    }

    function buildFavData() {
        const contacts = [];
        contactsList.querySelectorAll('.qr-contact-row').forEach(row => {
            const name  = row.querySelector('[data-field="name"]').value.trim();
            const phone = row.querySelector('[data-field="phone"]').value.trim();
            const blf   = row.querySelector('[data-field="blf"]').checked;
            if (!name && !phone) return;
            contacts.push({ name, phone, blf });
        });
        return contacts.length ? { contacts } : null;
    }

    function buildGenericQrData() {
        if (qrSubTab === 'url') {
            let url = document.getElementById('qr-url').value.trim();
            if (!url) return null;
            if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
            return url;
        }
        if (qrSubTab === 'text') {
            const text = document.getElementById('qr-text').value.trim();
            return text || null;
        }
        if (qrSubTab === 'contact') {
            const f     = id => document.getElementById(id).value.trim();
            const first = f('qr-c-first'), last  = f('qr-c-last'),
                  phone = f('qr-c-phone'), email = f('qr-c-email'),
                  org   = f('qr-c-org'),   url   = f('qr-c-url');
            if (!first && !last && !phone && !email) return null;
            return `BEGIN:VCARD\nVERSION:3.0\nFN:${first} ${last}\nN:${last};${first};;;\nORG:${org}\nTEL:${phone}\nEMAIL:${email}\nURL:${url}\nEND:VCARD`;
        }
        return null;
    }

    function buildPayload() {
        if (activeTab === 'sip') {
            const data = buildSipData();
            if (!data) return null;
            return {
                preview: JSON.stringify(data, null, 2),
                qrValue: JSON.stringify(data),
                downloadName: 'sip-config.json',
                downloadMime: 'application/json',
                downloadContent: JSON.stringify(data, null, 2),
                downloadLabel: '⬇ Télécharger le JSON',
                copyLabel: '📋 Copier le JSON',
            };
        }
        if (activeTab === 'favoris') {
            const data = buildFavData();
            if (!data) return null;
            return {
                preview: JSON.stringify(data, null, 2),
                qrValue: JSON.stringify(data),
                downloadName: 'favoris.json',
                downloadMime: 'application/json',
                downloadContent: JSON.stringify(data, null, 2),
                downloadLabel: '⬇ Télécharger le JSON',
                copyLabel: '📋 Copier le JSON',
            };
        }
        const value = buildGenericQrData();
        if (!value) return null;
        const names = { url: 'qrcode-url.txt', text: 'qrcode-texte.txt', contact: 'contact.vcf' };
        const mimes = { url: 'text/plain', text: 'text/plain', contact: 'text/vcard' };
        return {
            preview: value,
            qrValue: value,
            downloadName: names[qrSubTab],
            downloadMime: mimes[qrSubTab],
            downloadContent: value,
            downloadLabel: qrSubTab === 'contact' ? '⬇ Télécharger le .vcf' : '⬇ Télécharger le .txt',
            copyLabel: '📋 Copier',
        };
    }

    // ── Mise à jour de l'aperçu ──
    function updatePreview() {
        const payload = buildPayload();
        if (!payload) {
            canvas.style.display       = 'none';
            placeholder.style.display  = 'block';
            actions.style.display      = 'none';
            dataBox.style.display      = 'none';
            warningBox.style.display   = 'none';
            return;
        }

        dataBox.style.display      = 'block';
        dataBox.textContent        = payload.preview;
        actions.style.display      = 'flex';
        placeholder.style.display  = 'none';
        btnDownload.textContent    = payload.downloadLabel;
        btnCopy.textContent        = payload.copyLabel;

        const byteLength = new Blob([payload.qrValue]).size;
        const TOO_BIG    = 1800;

        if (byteLength > TOO_BIG) {
            warningBox.style.display = 'block';
            warningBox.textContent   = `⚠ Les données sont volumineuses (${byteLength} octets). Le QR code risque d'être difficile à scanner : préférez le téléchargement du fichier.`;
            canvas.style.display     = 'none';
            btnDownloadQr.disabled   = true;
            qr = null;
        } else {
            warningBox.style.display = 'none';
            btnDownloadQr.disabled   = false;
            try {
                if (!qr) qr = new QRious({ element: canvas, size: 280, level: 'M' });
                qr.value             = payload.qrValue;
                canvas.style.display = 'block';
            } catch {
                canvas.style.display     = 'none';
                warningBox.style.display = 'block';
                warningBox.textContent   = '⚠ Impossible de générer le QR code pour ces données. Téléchargez le fichier à la place.';
                btnDownloadQr.disabled   = true;
            }
        }
    }

    // ── Téléchargement du fichier (JSON / TXT / VCF) ──
    btnDownload.addEventListener('click', () => {
        const payload = buildPayload();
        if (!payload) return;
        const blob = new Blob([payload.downloadContent], { type: payload.downloadMime });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.download = payload.downloadName;
        a.href     = url;
        a.click();
        URL.revokeObjectURL(url);
    });

    // ── Téléchargement du QR code en PNG ──
    btnDownloadQr.addEventListener('click', () => {
        if (!qr || canvas.style.display === 'none') return;
        const payload = buildPayload();
        if (!payload) return;
        const a    = document.createElement('a');
        a.download = payload.downloadName.replace(/\.(json|txt|vcf)$/, '') + '-qrcode.png';
        a.href     = canvas.toDataURL('image/png');
        a.click();
    });

    // ── Copie dans le presse-papiers ──
    btnCopy.addEventListener('click', () => {
        const payload = buildPayload();
        if (!payload) return;
        const label = payload.copyLabel;
        navigator.clipboard.writeText(payload.preview).then(() => {
            btnCopy.textContent = '✅ Copié !';
            setTimeout(() => { btnCopy.textContent = label; }, 2000);
        });
    });

    updatePreview();
}
