/**
 * app.js - Point d'entrée JavaScript
 * Importe et initialise tous les composants
 */

import '../sass/app.scss';

// Sanitizer HTML — exposé globalement car le rendu Markdown vit dans des
// scripts inline (cortex/chat.blade.php et shared/conversation.blade.php).
// Les modules Vite s'exécutent avant DOMContentLoaded : la variable est donc
// disponible pour tout code qui s'exécute à partir de cet évènement.
import DOMPurify from 'dompurify';
window.DOMPurify = DOMPurify;

import { initBurger }        from './components/burger.js';
import { initDropdowns }     from './components/dropdown.js';
import { initTooltips }      from './components/tooltip.js';
import { initImagePreview }  from './components/imagePreview.js';
import { initConfirmDelete }  from './components/confirmDelete.js';
import { initTheme }          from './components/theme.js';
import { initPasswordToggle } from './components/passwordToggle.js';
import { initToasts }         from './components/toast.js';
import { initSortable }           from './components/sortable.js';
import { initBackgroundRemover }  from './components/backgroundRemover.js';
import { initImageConverter }     from './components/imageconverter.js';
import { initQrCode }             from './components/qrcode.js';
import { initCredentials }        from './components/credentials.js';
import { initChatbot }            from './components/chatbot.js';

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initBurger();
    initDropdowns();
    initTooltips();
    initImagePreview();
    initConfirmDelete();
    initPasswordToggle();
    initToasts();
    initSortable();
    initBackgroundRemover();
    initImageConverter();
    initQrCode();
    initCredentials();
    initChatbot();
});
