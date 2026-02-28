/**
 * app.js - Point d'entrée JavaScript
 * Importe et initialise tous les composants
 */

import '../sass/app.scss';

import { initBurger }        from './components/burger.js';
import { initDropdowns }     from './components/dropdown.js';
import { initTooltips }      from './components/tooltip.js';
import { initImagePreview }  from './components/imagePreview.js';
import { initConfirmDelete }  from './components/confirmDelete.js';
import { initTheme }          from './components/theme.js';
import { initPasswordToggle } from './components/passwordToggle.js';
import { initToasts }         from './components/toast.js';
import { initSortable }       from './components/sortable.js';

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
});
