import { showToast } from './toast.js';
import JSZip from 'jszip';

export function initImageConverter() {
    const dropZone          = document.getElementById('dropZone');
    if (!dropZone) return;

    const fileInput         = document.getElementById('fileInput');
    const filesContainer    = document.getElementById('filesContainer');
    const filesList         = document.getElementById('filesList');
    const formatSelect      = document.getElementById('formatSelect');
    const qualitySlider     = document.getElementById('qualitySlider');
    const qualityDisplay    = document.getElementById('qualityDisplay');
    const qualityContainer  = document.getElementById('qualityContainer');
    const formatInfo        = document.getElementById('formatInfo');
    const convertBtn        = document.getElementById('convertBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressFill      = document.getElementById('progressFill');
    const progressText      = document.getElementById('progressText');
    const resultsContainer  = document.getElementById('resultsContainer');
    const downloadList      = document.getElementById('downloadList');

    let selectedFiles  = [];
    let convertedFiles = [];

    // --- Drag & drop ---
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    dropZone.addEventListener('click', () => fileInput.click());

    // --- Boutons & contrôles statiques ---
    document.getElementById('file-picker-btn').addEventListener('click', () => fileInput.click());
    document.getElementById('clear-all-btn').addEventListener('click', clearAllFiles);
    document.getElementById('download-all-btn').addEventListener('click', downloadAll);

    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
    formatSelect.addEventListener('change', updateFormatOptions);
    qualitySlider.addEventListener('input', updateQualityDisplay);
    convertBtn.addEventListener('click', startConversion);

    // --- Délégation d'événements pour les éléments dynamiques ---
    filesList.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove');
        if (btn) removeFile(+btn.dataset.index);
    });

    downloadList.addEventListener('click', (e) => {
        const btn = e.target.closest('.download-btn');
        if (btn) downloadFile(+btn.dataset.index);
    });

    // --- Gestion des fichiers ---
    function handleFiles(files) {
        const imageFiles = Array.from(files).filter(f => f.type.startsWith('image/'));

        if (imageFiles.length === 0) {
            showToast('Veuillez sélectionner des fichiers images valides.', 'warning');
            return;
        }

        imageFiles.forEach(file => {
            if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
            }
        });

        updateFilesDisplay();
    }

    function updateFilesDisplay() {
        if (selectedFiles.length === 0) {
            filesContainer.style.display = 'none';
            resetDropZone();
            return;
        }

        filesContainer.style.display = 'block';
        filesList.innerHTML = '';
        selectedFiles.forEach((file, index) => createFileItem(file, index));
        updateDropZoneSuccess();
    }

    function createFileItem(file, index) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';

        const img = document.createElement('img');
        img.className = 'file-preview';
        img.alt = file.name;

        const reader = new FileReader();
        reader.onload = (e) => { img.src = e.target.result; };
        reader.readAsDataURL(file);

        const fileFormat = file.name.split('.').pop().toUpperCase();
        const fileSize   = formatFileSize(file.size);

        fileItem.innerHTML = `
            <div class="file-info">
                <div class="file-name">${file.name}</div>
                <div class="file-details">
                    <span class="file-format">${fileFormat}</span>
                    <span class="file-size">${fileSize}</span>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn-small btn-remove" data-index="${index}">Supprimer</button>
            </div>
        `;

        fileItem.insertBefore(img, fileItem.firstChild);
        filesList.appendChild(fileItem);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k     = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i     = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFilesDisplay();
    }

    function clearAllFiles() {
        selectedFiles = [];
        updateFilesDisplay();
    }

    function resetDropZone() {
        dropZone.innerHTML = `
            <div class="drop-icon">📸</div>
            <div class="drop-text">Déposez vos images ici</div>
            <div class="drop-subtext">ou cliquez pour sélectionner des fichiers</div>
        `;
    }

    function updateDropZoneSuccess() {
        dropZone.innerHTML = `
            <div class="drop-icon">✅</div>
            <div class="drop-text">${selectedFiles.length} image(s) sélectionnée(s)</div>
            <div class="drop-subtext">Cliquez pour ajouter d'autres images</div>
        `;
    }

    // --- Options de conversion ---
    function updateFormatOptions() {
        const selectedFormat     = formatSelect.value;
        const formatsWithQuality = ['jpg', 'webp'];

        qualityContainer.style.display = formatsWithQuality.includes(selectedFormat) ? 'flex' : 'none';

        const formatInfos = {
            'webp': 'Format moderne avec excellente compression. Réduit la taille de 25-50% par rapport au JPG.',
            'jpg':  'Format universel, compatible avec tous les navigateurs et appareils.',
            'png':  'Idéal pour les images avec transparence ou les captures d\'écran.',
            'gif':  'Parfait pour les animations simples et les images avec peu de couleurs.',
            'bmp':  'Format non compressé, fichiers volumineux mais qualité maximale.',
            'tiff': 'Format professionnel pour l\'impression et l\'archivage.',
        };

        formatInfo.textContent   = formatInfos[selectedFormat] || '';
        formatInfo.style.display = 'block';
    }

    function updateQualityDisplay() {
        qualityDisplay.textContent = qualitySlider.value + '%';
    }

    // --- Conversion ---
    function startConversion() {
        if (selectedFiles.length === 0) {
            showToast('Veuillez sélectionner au moins une image à convertir.', 'warning');
            return;
        }

        const outputFormat = formatSelect.value;
        const quality      = parseInt(qualitySlider.value) / 100;

        convertBtn.disabled    = true;
        convertBtn.textContent = '🔄 Conversion en cours...';
        progressContainer.style.display = 'flex';
        resultsContainer.style.display  = 'none';

        convertedFiles = [];
        convertImages(selectedFiles, outputFormat, quality);
    }

    async function convertImages(files, outputFormat, quality) {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            progressFill.style.width = `${(i / files.length) * 100}%`;
            progressText.textContent = `Conversion de ${file.name}... (${i + 1}/${files.length})`;

            try {
                const convertedBlob = await convertSingleImage(file, outputFormat, quality);
                const originalName  = file.name.substring(0, file.name.lastIndexOf('.'));
                convertedFiles.push({
                    name:         `${originalName}.${outputFormat}`,
                    blob:         convertedBlob,
                    originalSize: file.size,
                    newSize:      convertedBlob.size,
                });
            } catch (error) {
                console.error(`Erreur lors de la conversion de ${file.name}:`, error);
            }

            await new Promise(resolve => setTimeout(resolve, 100));
        }

        progressFill.style.width = '100%';
        progressText.textContent = 'Conversion terminée !';
        setTimeout(showResults, 500);
    }

    function convertSingleImage(file, outputFormat, quality) {
        return new Promise((resolve, reject) => {
            const img    = new Image();
            const canvas = document.createElement('canvas');
            const ctx    = canvas.getContext('2d');

            img.onload = function () {
                canvas.width  = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);

                let mimeType      = 'image/png';
                let canvasQuality = quality;

                switch (outputFormat) {
                    case 'jpg':  mimeType = 'image/jpeg'; break;
                    case 'webp': mimeType = 'image/webp'; break;
                    case 'png':
                    case 'gif':
                    case 'bmp':
                    case 'tiff': canvasQuality = undefined; break;
                }

                canvas.toBlob(
                    blob => blob ? resolve(blob) : reject(new Error('Impossible de convertir l\'image')),
                    mimeType,
                    canvasQuality
                );
            };

            img.onerror = () => reject(new Error('Impossible de charger l\'image'));
            img.src = URL.createObjectURL(file);
        });
    }

    function showResults() {
        convertBtn.disabled    = false;
        convertBtn.textContent = '🔄 Convertir les images';
        progressContainer.style.display = 'none';
        resultsContainer.style.display  = 'flex';

        downloadList.innerHTML = '';

        convertedFiles.forEach((file, index) => {
            const item  = document.createElement('div');
            item.className = 'download-item';

            const ratio     = (file.originalSize - file.newSize) / file.originalSize * 100;
            const ratioText = ratio > 0
                ? `📉 -${ratio.toFixed(1)}%`
                : `📈 +${Math.abs(ratio).toFixed(1)}%`;

            item.innerHTML = `
                <div class="download-info">
                    <div class="download-icon">🖼️</div>
                    <div class="download-details">
                        <div class="download-name">${file.name}</div>
                        <div class="download-size">${formatFileSize(file.newSize)} ${ratioText}</div>
                    </div>
                </div>
                <button class="download-btn" data-index="${index}">Télécharger</button>
            `;

            downloadList.appendChild(item);
        });
    }

    function downloadFile(index) {
        if (index < 0 || index >= convertedFiles.length) {
            showToast('Fichier non trouvé.', 'error');
            return;
        }
        const file = convertedFiles[index];
        downloadBlob(file.blob, file.name);
    }

    async function downloadAll() {
        if (convertedFiles.length === 0) {
            showToast('Aucun fichier à télécharger.', 'warning');
            return;
        }

        try {
            const zip = new JSZip();
            convertedFiles.forEach(file => zip.file(file.name, file.blob));

            const zipBlob   = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 } });
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            downloadBlob(zipBlob, `images_converties_${timestamp}.zip`);

        } catch (error) {
            console.error('Erreur ZIP:', error);
            showToast('Erreur lors de la création du ZIP. Téléchargez les fichiers individuellement.', 'error');
        }
    }

    function downloadBlob(blob, fileName) {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = fileName;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    // Initialisation
    updateFormatOptions();
    updateQualityDisplay();
}
