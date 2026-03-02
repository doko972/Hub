import { showToast } from './toast.js';

export function initBackgroundRemover() {
    const form = document.getElementById('bg-remover-form');
    if (!form) return;

    const input            = document.getElementById('image-input');
    const uploadZone       = document.getElementById('upload-zone');
    const placeholder      = document.getElementById('upload-placeholder');
    const imagePreview     = document.getElementById('image-preview');
    const submitBtn        = document.getElementById('submit-btn');
    const resetBtn         = document.getElementById('reset-btn');
    const resultZone       = document.getElementById('result-zone');
    const originalPreview  = document.getElementById('original-preview');
    const resultPreviewImg = document.getElementById('result-preview-img');
    const downloadBtn      = document.getElementById('download-btn');

    // Réinitialise le formulaire à l'état initial
    function reset() {
        form.reset();
        imagePreview.src    = '';
        imagePreview.hidden = true;
        placeholder.hidden  = false;
        submitBtn.disabled  = true;
        resetBtn.hidden     = true;
        resultZone.hidden   = true;
        resultPreviewImg.src = '';
        originalPreview.src  = '';
        downloadBtn.href     = '#';
    }

    resetBtn.addEventListener('click', reset);

    // Prévisualisation image sélectionnée
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;

        const url = URL.createObjectURL(file);
        imagePreview.src    = url;
        imagePreview.hidden = false;
        placeholder.hidden  = true;
        submitBtn.disabled  = false;
        resetBtn.hidden     = false;
        originalPreview.src = url;
    });

    // Drag & drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('upload-zone--drag');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('upload-zone--drag');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('upload-zone--drag');
        const file = e.dataTransfer.files[0];
        if (!file) return;

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    });

    // Soumission du formulaire via fetch (pas de rechargement)
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        submitBtn.disabled = true;
        submitBtn.textContent = 'Traitement en cours...';

        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) throw new Error('Erreur lors du traitement.');

            const blob = await response.blob();
            const url  = URL.createObjectURL(blob);

            resultPreviewImg.src = url;
            downloadBtn.href     = url;
            resultZone.hidden    = false;
            resultZone.scrollIntoView({ behavior: 'smooth' });

        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            submitBtn.disabled   = false;
            submitBtn.textContent = 'Supprimer l\'arrière-plan';
        }
    });
}
