(function () {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadList = document.getElementById('uploadList');

    // Config from Server
    const LIMITS = window.APP_LIMITS || {
        maxFiles: 100, // Updated Default
        maxImageBytes: 5 * 1024 * 1024 * 1024,
        maxVideoBytes: 5 * 1024 * 1024 * 1024,
        maxImageGb: 5,
        maxVideoGb: 5
    };

    // Queue State
    const MAX_CONCURRENT = 4;
    let queue = [];
    let activeUploads = 0;

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;

    // Initialize UI for device
    function initUI() {
        if (isMobile) {
            // Mobile specific initial adjustments if needed
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
                <div class="upload-status-badge">V čakalni vrsti</div>
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
            xhr: null,
            status: 'queued' // queued, uploading, done, error, cancelled
        };
    }

    function processQueue() {
        if (activeUploads >= MAX_CONCURRENT) return;

        const nextItem = queue.find(i => i.status === 'queued');
        if (nextItem) {
            uploadFile(nextItem);
            processQueue(); // Try to start more if slots available
        }
    }

    function uploadFile(uploadItem) {
        uploadItem.status = 'uploading';
        activeUploads++;

        const { file, element, fill, badge, percentEl, speedEl, etaEl, cancelBtn } = uploadItem;

        // 1. Validate Size based on type
        let isVideo = file.type.startsWith('video/');
        let isImage = file.type.startsWith('image/');
        let limitBytes = isVideo ? LIMITS.maxVideoBytes : LIMITS.maxImageBytes;
        let limitGb = isVideo ? LIMITS.maxVideoGb : LIMITS.maxImageGb;

        // Fallback for unknown types
        if (!isVideo && !isImage) {
            limitBytes = LIMITS.maxImageBytes;
            limitGb = LIMITS.maxImageGb;
        }

        if (file.size > limitBytes) {
            activeUploads--;
            markError(uploadItem, `Prevelika datoteka (Max ${limitGb} GB)`);
            processQueue();
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
            activeUploads--;
            element.classList.remove('uploading');

            // Try to parse JSON even if status is error, as backend might return structured error
            let response = null;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                // Not JSON (fatal error or HTML)
                if (xhr.status >= 200 && xhr.status < 300) {
                     markError(uploadItem, 'Neveljaven odgovor strežnika');
                } else {
                     markError(uploadItem, `HTTP Error ${xhr.status}`);
                }
                processQueue();
                return;
            }

            // Backend structure: { results: [ { success: true/false, error: '...', ... } ] }
            // or standard error structure

            if (xhr.status >= 200 && xhr.status < 300) {
                const result = response.results?.[0];
                if (result && (result.success || !result.error)) {
                    markSuccess(uploadItem);
                } else {
                    markError(uploadItem, result?.error || 'Neznana napaka');
                }
            } else {
                // Backend returned 4xx/5xx with JSON error
                const result = response.results?.[0];
                const msg = result?.error || response.error || `HTTP ${xhr.status}`;
                markError(uploadItem, msg);
            }

            processQueue();
        };

        xhr.onerror = () => {
            activeUploads--;
            element.classList.remove('uploading');
            markError(uploadItem, 'Napaka omrežja');
            processQueue();
        };

        xhr.onabort = () => {
            activeUploads--;
            element.classList.remove('uploading');
            markError(uploadItem, 'Preklicano');
            processQueue();
        };

        cancelBtn.addEventListener('click', () => {
            if (xhr.readyState > 0 && xhr.readyState < 4) {
                xhr.abort();
            } else {
                element.remove();
                // If it was queued, remove from queue
                const idx = queue.indexOf(uploadItem);
                if (idx > -1) queue.splice(idx, 1);
            }
        });

        xhr.open('POST', '/upload.php', true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(formData);
    }

    function markSuccess(item) {
        item.status = 'done';
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
        item.status = 'error';
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

        // Limit concurrent selection count if needed, but 100 is allowed
        if (fileList.length > LIMITS.maxFiles) {
            alert(`Izbrali ste preveč datotek. Max: ${LIMITS.maxFiles}. Naloženo bo prvih ${LIMITS.maxFiles}.`);
        }

        const filesToUpload = Array.from(fileList).slice(0, LIMITS.maxFiles);

        filesToUpload.forEach(file => {
            const uiItem = createUploadItem(file);
            queue.push(uiItem);
        });

        processQueue();
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
