(() => {
  'use strict';

  const inventory = document.querySelector('.chr-app');
  if (!inventory) return;

  const form = inventory.querySelector('#chr-inventory-filters');
  const search = inventory.querySelector('#chr-search');
  const clear = inventory.querySelector('#chr-search-clear');
  const list = inventory.querySelector('#chr-search-options');
  const searchField = inventory.querySelector('.ops-search-field');
  const initialQuery = search?.defaultValue.trim() || '';
  let names = [];
  let active = -1;

  try {
    const saved = JSON.parse(inventory.querySelector('#chr-search-data')?.dataset.candidates || '[]');
    if (Array.isArray(saved)) names = saved.filter((name) => typeof name === 'string');
  } catch {
    // Le formulaire, les boutons de copie et le rafraîchissement restent utilisables.
  }

  const normalize = (text) => text.normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();

  const closeSuggestions = () => {
    if (!list || !search) return;
    list.hidden = true;
    list.replaceChildren();
    search.setAttribute('aria-expanded', 'false');
    search.removeAttribute('aria-activedescendant');
    active = -1;
  };

  const updateActive = (index) => {
    const options = Array.from(list.children);
    active = index;
    options.forEach((option, position) => {
      const selected = position === index;
      option.classList.toggle('is-active', selected);
      option.setAttribute('aria-selected', String(selected));
    });
    if (index >= 0) {
      search.setAttribute('aria-activedescendant', options[index].id);
      options[index].scrollIntoView({ block: 'nearest' });
    } else {
      search.removeAttribute('aria-activedescendant');
    }
  };

  const submitFilters = () => {
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
  };

  const selectSuggestion = (name) => {
    search.value = name;
    closeSuggestions();
    submitFilters();
  };

  const renderSuggestions = () => {
    if (!search || !list) return;
    const query = normalize(search.value);
    if (query.length < 2) {
      closeSuggestions();
      return;
    }

    const matching = names.filter((name) => normalize(name).includes(query))
      .sort((a, b) => {
        const aStarts = normalize(a).startsWith(query);
        const bStarts = normalize(b).startsWith(query);
        return Number(bStarts) - Number(aStarts) || a.localeCompare(b, 'fr');
      }).slice(0, 8);
    if (!matching.length) {
      closeSuggestions();
      return;
    }

    const fragment = document.createDocumentFragment();
    matching.forEach((name, index) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.id = `chr-suggestion-${index}`;
      option.className = 'ops-suggestion';
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.textContent = name;
      option.addEventListener('click', () => selectSuggestion(name));
      fragment.appendChild(option);
    });
    list.replaceChildren(fragment);
    list.hidden = false;
    search.setAttribute('aria-expanded', 'true');
    active = -1;
    search.removeAttribute('aria-activedescendant');
  };

  if (form && search && clear && list && searchField) {
    search.addEventListener('input', () => {
      clear.hidden = search.value.trim() === '';
      if (initialQuery !== '' && search.value.trim() === '') {
        closeSuggestions();
        submitFilters();
        return;
      }
      renderSuggestions();
    });
    search.addEventListener('focus', renderSuggestions);
    search.addEventListener('keydown', (event) => {
      if (list.hidden) return;
      const count = list.children.length;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        updateActive((active + 1) % count);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        updateActive(active < 0 ? count - 1 : (active + count - 1) % count);
      } else if (event.key === 'Enter' && active >= 0) {
        event.preventDefault();
        selectSuggestion(list.children[active].textContent);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeSuggestions();
      }
    });
    document.addEventListener('click', (event) => {
      if (!searchField.contains(event.target)) closeSuggestions();
    });
    form.querySelectorAll('select').forEach((select) => {
      select.addEventListener('change', submitFilters);
    });
  }

  inventory.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-copy-kind][data-copy-ip]');
    if (!button || !inventory.contains(button)) return;

    const ip = button.dataset.copyIp;
    const command = button.dataset.copyKind === 'ssh';
    const value = command ? `ssh -p 45222 a2dmin@${ip}` : ip;
    const cell = button.closest('td');
    const status = cell?.querySelector('.ops-copy-status');
    const manual = cell?.querySelector('.ops-manual-copy');
    if (!status || !manual) return;

    status.textContent = '';
    manual.hidden = true;
    let copied = false;
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      try {
        await navigator.clipboard.writeText(value);
        copied = true;
      } catch {
        // Certains navigateurs refusent cette API même sur une page HTTPS.
      }
    }
    if (!copied) {
      manual.value = value;
      manual.hidden = false;
      manual.focus({ preventScroll: true });
      manual.select();
      try {
        copied = Boolean(document.execCommand('copy'));
      } catch {
        copied = false;
      }
    }

    if (copied) {
      manual.hidden = true;
      status.textContent = command ? 'Commande SSH copiée.' : 'Adresse IP copiée.';
      button.focus({ preventScroll: true });
    } else {
      status.textContent = 'Copie automatique bloquée : texte sélectionné ci-dessous, faites Ctrl+C.';
      manual.focus({ preventScroll: true });
      manual.select();
    }
  });

  const refreshForm = inventory.querySelector('.ops-sync form');
  refreshForm?.addEventListener('submit', () => {
    const button = refreshForm.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Actualisation en cours…';
    refreshForm.setAttribute('aria-busy', 'true');
  });
})();
