(function () {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadList = document.getElementById('uploadList');

    // Config
    const MAX_FILES = 10;
    const MAX_SIZE_BYTES = 50 * 1024 * 1024 * 1024; // 50GB

    // State
    let activeUploads = 0;
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;

    // Initialize UI for device
    function initUI() {
        if (isMobile) {
            // Mobile specific initial adjustments if needed
            // CSS handles most of layout
        }
    }

    // Format helpers
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${(bytes / Math.pow(k, i)).toFixed(2)} ${sizes[i]}`;
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) return '--';
        if (seconds < 60) return `${Math.round(seconds)}s`;
        const m = Math.floor(seconds / 60);
        const s = Math.round(seconds % 60);
        return `${m}m ${s}s`;
    }

    function getFileIcon(type) {
        if (type.startsWith('image/')) return '🖼️';
        if (type.startsWith('video/')) return '🎬';
        return '📄';
    }

    // Main Upload Logic
    function createUploadItem(file) {
        const id = 'file-' + Math.random().toString(36).substr(2, 9);
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.id = id;

        const icon = getFileIcon(file.type);

        item.innerHTML = `
            <div class="file-header">
                <div class="file-info-main">
                    <div class="file-type-icon">${icon}</div>
                    <div class="file-details-text">
                        <span class="file-name" title="${file.name}">${file.name}</span>
                        <span class="file-size">${formatBytes(file.size)}</span>
                    </div>
                </div>
                <div class="upload-status-badge">Čaka</div>
            </div>

            <div class="file-progress-wrapper">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" style="width: 0%"></div>
                </div>
                <div class="progress-stats">
                    <span class="stats-percent">0%</span>
                    <span class="stats-speed">-- MB/s</span>
                    <span class="stats-eta">--</span>
                </div>
            </div>

            <button class="progress-cancel-btn" title="Prekliči">✕</button>
        `;

        uploadList.prepend(item); // Add new files to top

        return {
            element: item,
            file: file,
            fill: item.querySelector('.progress-bar-fill'),
            badge: item.querySelector('.upload-status-badge'),
            percentEl: item.querySelector('.stats-percent'),
            speedEl: item.querySelector('.stats-speed'),
            etaEl: item.querySelector('.stats-eta'),
            cancelBtn: item.querySelector('.progress-cancel-btn'),
            startTime: 0,
            lastLoaded: 0,
            lastTime: 0,
            xhr: null
        };
    }

    function startUpload(uploadItem) {
        const { file, element, fill, badge, percentEl, speedEl, etaEl, cancelBtn } = uploadItem;

        // 1. Validate Size
        if (file.size > MAX_SIZE_BYTES) {
            markError(uploadItem, 'Prevelika datoteka (>50GB)');
            return;
        }

        badge.textContent = 'Nalaganje';
        element.classList.add('uploading');

        const xhr = new XMLHttpRequest();
        uploadItem.xhr = xhr;

        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        formData.append('csrf_token', csrfToken || '');
        formData.append('files[]', file); // Backend expects array

        uploadItem.startTime = performance.now();
        uploadItem.lastTime = uploadItem.startTime;

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const now = performance.now();
                const loaded = e.loaded;
                const total = e.total;
                const percent = Math.min(100, (loaded / total) * 100);

                fill.style.width = `${percent}%`;
                percentEl.textContent = `${Math.round(percent)}%`;

                // Update speed/eta every 500ms
                const timeDiff = (now - uploadItem.lastTime) / 1000; // seconds
                if (timeDiff >= 0.5 || percent === 100) {
                    const bytesDiff = loaded - uploadItem.lastLoaded;
                    const speedBytesPerSec = bytesDiff / timeDiff; // B/s

                    // Display speed
                    speedEl.textContent = `${formatBytes(speedBytesPerSec)}/s`;

                    // ETA
                    if (speedBytesPerSec > 0) {
                        const remainingBytes = total - loaded;
                        const etaSeconds = remainingBytes / speedBytesPerSec;
                        etaEl.textContent = formatTime(etaSeconds);
                    }

                    uploadItem.lastLoaded = loaded;
                    uploadItem.lastTime = now;
                }
            }
        };

        xhr.onload = () => {
            element.classList.remove('uploading');
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const resp = JSON.parse(xhr.responseText);
                    const result = resp.results?.[0]; // We sent one file

                    if (result && result.success) {
                        markSuccess(uploadItem);
                    } else {
                        markError(uploadItem, result?.error || 'Napaka strežnika');
                    }
                } catch (e) {
                    markError(uploadItem, 'Neveljaven odgovor');
                }
            } else {
                markError(uploadItem, `HTTP ${xhr.status}`);
            }
        };

        xhr.onerror = () => {
            element.classList.remove('uploading');
            markError(uploadItem, 'Napaka omrežja');
        };

        xhr.onabort = () => {
            element.classList.remove('uploading');
            markError(uploadItem, 'Preklicano');
        };

        cancelBtn.addEventListener('click', () => {
            if (xhr.readyState > 0 && xhr.readyState < 4) {
                xhr.abort();
            } else {
                // If already done or error, just remove from list?
                // For now, let's allow removing the card
                element.remove();
            }
        });

        xhr.open('POST', '/upload.php', true);
        xhr.send(formData);
    }

    function markSuccess(item) {
        item.element.classList.add('success');
        item.badge.textContent = 'Končano';
        item.fill.style.width = '100%';
        item.percentEl.textContent = '100%';
        item.speedEl.textContent = '';
        item.etaEl.textContent = 'Uspešno';
        item.cancelBtn.textContent = '✓';
        item.cancelBtn.disabled = true;
    }

    function markError(item, msg) {
        item.element.classList.add('error');
        item.badge.textContent = 'Napaka';
        item.etaEl.textContent = msg;
        item.speedEl.textContent = '';
    }

    function handleFiles(fileList) {
        // If mobile, switch UI to compact mode for dropzone to save space
        if (isMobile && uploadList.children.length === 0) {
            dropZone.classList.add('compact');
        }

        // Limit concurrent selection count
        const filesToUpload = Array.from(fileList).slice(0, MAX_FILES);

        if (fileList.length > MAX_FILES) {
            alert(`Izbrali ste preveč datotek. Naloženo bo prvih ${MAX_FILES}.`);
        }

        filesToUpload.forEach(file => {
            const uiItem = createUploadItem(file);
            startUpload(uiItem);
        });
    }

    // Event Listeners
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('active');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('active');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('active');
        if (e.dataTransfer.files.length) {
            handleFiles(e.dataTransfer.files);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFiles(fileInput.files);
            fileInput.value = ''; // Reset to allow selecting same file again
        }
    });

    // Init
    initUI();

})();
