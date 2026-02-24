/**
 * imagePreview.js
 * Prévisualisation d'image avant upload (formulaire admin)
 */

export function initImagePreview() {
    const inputs = document.querySelectorAll('[data-image-preview]');

    inputs.forEach((input) => {
        const previewId = input.dataset.imagePreview;
        const preview   = document.getElementById(previewId);
        const wrapper   = input.closest('.form-upload');

        if (!preview) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';

                // Masquer les initiales si présentes (page profil)
                const initials = document.getElementById('avatar-initials');
                if (initials) initials.style.display = 'none';

                // Masquer l'icône/texte d'upload si présents (formulaire admin)
                if (wrapper) {
                    const uploadIcon = wrapper.querySelector('.form-upload__icon');
                    const uploadText = wrapper.querySelector('.form-upload__text');
                    if (uploadIcon) uploadIcon.style.display = 'none';
                    if (uploadText) uploadText.style.display = 'none';
                }

                // Soumettre automatiquement si data-auto-submit est défini
                const autoSubmitId = input.dataset.autoSubmit;
                if (autoSubmitId) {
                    const form = document.getElementById(autoSubmitId);
                    if (form) form.submit();
                }
            };
            reader.readAsDataURL(file);
        });

        // Clic sur la zone d'upload déclenche l'input
        if (wrapper) {
            wrapper.addEventListener('click', (e) => {
                if (e.target !== input) {
                    input.click();
                }
            });
        }
    });
}
