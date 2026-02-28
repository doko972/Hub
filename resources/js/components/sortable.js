/**
 * sortable.js — Drag & drop des lignes de tableau (SortableJS)
 * Usage : <tbody data-sortable data-sortable-url="/admin/tools/reorder" data-page="{{ $tools->currentPage() }}" data-per-page="{{ $tools->perPage() }}">
 *   <tr data-id="{{ $tool->id }}">...</tr>
 * </tbody>
 */

import Sortable from 'sortablejs';
import { showToast } from './toast.js';

export function initSortable() {
    document.querySelectorAll('[data-sortable]').forEach(container => {
        const url     = container.dataset.sortableUrl;
        const page    = parseInt(container.dataset.page    || 1);
        const perPage = parseInt(container.dataset.perPage || 20);
        const offset  = (page - 1) * perPage;

        Sortable.create(container, {
            animation:   150,
            handle:      '.drag-handle',
            ghostClass:  'sortable-ghost',
            chosenClass: 'sortable-chosen',

            onEnd: async () => {
                const order = [...container.querySelectorAll('[data-id]')]
                    .map((el, index) => ({ id: el.dataset.id, sort_order: offset + index }));

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ order }),
                    });
                    if (!res.ok) throw new Error();
                } catch {
                    showToast('Erreur lors de la sauvegarde de l\'ordre.', 'error');
                }
            },
        });
    });
}
