(function () {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadList = document.getElementById('uploadList');

    // Config from Server
    const LIMITS = window.APP_LIMITS || {
        maxFiles: 100,
        maxImageBytes: 5 * 1024 * 1024 * 1024,
        maxVideoBytes: 5 * 1024 * 1024 * 1024,
        maxImageGb: 5,
        maxVideoGb: 5
    };

    // Queue State
    const MAX_CONCURRENT = 4;
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks
    let queue = [];
    let activeUploads = 0;

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth < 768;

    function initUI() {
        if (isMobile) {
            // Mobile specific adjustments
        }
    }

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

        uploadList.prepend(item);

        return {
            element: item,
            file: file,
            fill: item.querySelector('.progress-bar-fill'),
            badge: item.querySelector('.upload-status-badge'),
            percentEl: item.querySelector('.stats-percent'),
            speedEl: item.querySelector('.stats-speed'),
            etaEl: item.querySelector('.stats-eta'),
            cancelBtn: item.querySelector('.progress-cancel-btn'),
            uploadId: Math.random().toString(36).substr(2) + Date.now().toString(36),
            startTime: 0,
            lastLoaded: 0,
            lastTime: 0,
            totalUploaded: 0,
            xhr: null,
            status: 'queued',
            currentChunk: 0,
            totalChunks: Math.ceil(file.size / CHUNK_SIZE)
        };
    }

    function processQueue() {
        if (activeUploads >= MAX_CONCURRENT) return;

        const nextItem = queue.find(i => i.status === 'queued');
        if (nextItem) {
            uploadFile(nextItem);
            processQueue();
        }
    }

    function uploadFile(uploadItem) {
        uploadItem.status = 'uploading';
        activeUploads++;

        const { file, element, badge, cancelBtn } = uploadItem;

        // Validation
        let isVideo = file.type.startsWith('video/');
        let isImage = file.type.startsWith('image/');
        let limitBytes = isVideo ? LIMITS.maxVideoBytes : LIMITS.maxImageBytes;
        let limitGb = isVideo ? LIMITS.maxVideoGb : LIMITS.maxImageGb;

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

        badge.textContent = 'Nalaganje...';
        element.classList.add('uploading');
        uploadItem.startTime = performance.now();
        uploadItem.lastTime = uploadItem.startTime;

        uploadNextChunk(uploadItem);

        cancelBtn.addEventListener('click', () => {
             if (uploadItem.xhr) uploadItem.xhr.abort();
             if (uploadItem.status === 'queued') {
                 element.remove();
                 const idx = queue.indexOf(uploadItem);
                 if (idx > -1) queue.splice(idx, 1);
             }
        });
    }

    function uploadNextChunk(item) {
        const start = item.currentChunk * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, item.file.size);
        const blob = item.file.slice(start, end);

        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        formData.append('csrf_token', csrfToken || '');

        // Chunk metadata
        formData.append('file_data', blob, item.file.name); // Using 'file_data' instead of 'files[]' for single chunk
        formData.append('upload_id', item.uploadId);
        formData.append('chunk_index', item.currentChunk);
        formData.append('total_chunks', item.totalChunks);
        formData.append('file_name', item.file.name);
        formData.append('file_type', item.file.type);
        formData.append('file_size', item.file.size); // Total size

        const xhr = new XMLHttpRequest();
        item.xhr = xhr;

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                // Calculate total progress including previous chunks
                const chunkLoaded = e.loaded;
                const totalLoaded = (item.currentChunk * CHUNK_SIZE) + chunkLoaded;
                const percent = Math.min(100, (totalLoaded / item.file.size) * 100);

                updateProgress(item, totalLoaded, percent);
            }
        };

        xhr.onload = () => {
            // Handle response
            let response = null;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                activeUploads--;
                markError(item, 'Invalid JSON response');
                processQueue();
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300 && response.success) {
                item.currentChunk++;
                if (item.currentChunk < item.totalChunks) {
                    // Next chunk
                    uploadNextChunk(item);
                } else {
                    // Done
                    activeUploads--;
                    markSuccess(item);
                    processQueue();
                }
            } else {
                activeUploads--;
                markError(item, response.error || `HTTP ${xhr.status}`);
                processQueue();
            }
        };

        xhr.onerror = () => {
            activeUploads--;
            markError(item, 'Network Error');
            processQueue();
        };

        xhr.open('POST', '/api/upload.php', true); // Sending directly to API
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(formData);
    }

    function updateProgress(item, loaded, percent) {
        const now = performance.now();
        item.fill.style.width = `${percent}%`;
        item.percentEl.textContent = `${Math.round(percent)}%`;

        const timeDiff = (now - item.lastTime) / 1000;
        if (timeDiff >= 0.5 || percent === 100) {
            const bytesDiff = loaded - item.totalUploaded; // Bytes since last update
            const speed = bytesDiff / timeDiff; // B/s

            if (speed > 0) {
                 item.speedEl.textContent = `${formatBytes(speed)}/s`;
                 const remaining = item.file.size - loaded;
                 const eta = remaining / speed;
                 item.etaEl.textContent = formatTime(eta);
            }

            item.lastTime = now;
            item.totalUploaded = loaded;
        }
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
        if (isMobile && uploadList.children.length === 0) {
            dropZone.classList.add('compact');
        }

        if (fileList.length > LIMITS.maxFiles) {
             alert(`Max ${LIMITS.maxFiles} files.`);
        }
        const filesToUpload = Array.from(fileList).slice(0, LIMITS.maxFiles);

        filesToUpload.forEach(file => {
            const uiItem = createUploadItem(file);
            queue.push(uiItem);
        });

        processQueue();
    }

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('active'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('active'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('active');
        if (e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFiles(fileInput.files);
            fileInput.value = '';
        }
    });

    initUI();
})();
