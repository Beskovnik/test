(function () {
    const uploader = document.querySelector('.uploader');
    if (!uploader) return;

    const dropZone = uploader.querySelector('.drop-zone');
    const input = dropZone.querySelector('input[type="file"]');
    const list = uploader.querySelector('.upload-list');
    const maxFiles = parseInt(uploader.dataset.max || '10', 10);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) return '--';
        if (seconds < 60) return `${Math.round(seconds)}s`;
        const m = Math.floor(seconds / 60);
        const s = Math.round(seconds % 60);
        return `${m}m ${s}s`;
    }

    function uploadFile(file) {
        const item = document.createElement('div');
        item.className = 'upload-item card';
        item.innerHTML = `
            <div class="file-info">
                <div class="file-icon">📄</div>
                <div class="file-details">
                    <div class="file-name"><strong>${file.name}</strong></div>
                    <div class="file-meta">${formatBytes(file.size)}</div>
                </div>
            </div>
            <div class="progress-container">
                <div class="progress-bar"><div class="fill"></div></div>
                <div class="progress-text">
                    <span class="percent">0%</span>
                    <span class="speed">0 KB/s</span>
                    <span class="eta">ETA --</span>
                </div>
            </div>
            <div class="status-icon">⏳</div>
        `;
        list.appendChild(item);

        const fill = item.querySelector('.fill');
        const percentEl = item.querySelector('.percent');
        const speedEl = item.querySelector('.speed');
        const etaEl = item.querySelector('.eta');
        const statusIcon = item.querySelector('.status-icon');
        const startTime = performance.now();
        let lastLoaded = 0;
        let lastTime = startTime;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken || '');
        formData.append('files[]', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/upload.php');
        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;

            const now = performance.now();
            const loaded = event.loaded;
            const total = event.total;
            const percent = Math.round((loaded / total) * 100);

            fill.style.width = `${percent}%`;
            percentEl.textContent = `${percent}%`;

            // Calculate speed (moving average could be better but simple diff is ok)
            const timeDiff = (now - lastTime) / 1000;
            if (timeDiff > 0.5) { // Update every 0.5s
                const bytesDiff = loaded - lastLoaded;
                const speed = bytesDiff / timeDiff;
                speedEl.textContent = `${formatBytes(speed)}/s`;

                const remaining = total - loaded;
                const eta = remaining / Math.max(speed, 1);
                etaEl.textContent = `ETA ${formatTime(eta)}`;

                lastLoaded = loaded;
                lastTime = now;
            }
        });

        xhr.onload = () => {
            if (xhr.status === 200) {
                try {
                    const resp = JSON.parse(xhr.responseText);
                    // Check if *this* file succeeded in the batch response?
                    // The backend returns {results: [...]}.
                    // Since we upload 1 by 1 here (files[] has 1 file), results[0] is ours.
                    const result = resp.results?.[0];
                    if (result && result.success) {
                        statusIcon.textContent = '✅';
                        item.classList.add('success');
                        fill.style.background = 'var(--accent)';
                    } else {
                        throw new Error(result?.error || 'Unknown error');
                    }
                } catch (e) {
                    statusIcon.textContent = '❌';
                    item.classList.add('error');
                    etaEl.textContent = e.message;
                }
            } else {
                statusIcon.textContent = '❌';
                item.classList.add('error');
            }
            // Reset fields
            speedEl.textContent = '';
            // etaEl.textContent = '';
        };

        xhr.onerror = () => {
            statusIcon.textContent = '❌';
            item.classList.add('error');
            etaEl.textContent = 'Network Error';
        };

        xhr.send(formData);
    }

    function handleFiles(files) {
        const listFiles = Array.from(files).slice(0, maxFiles);
        listFiles.forEach(uploadFile);
    }

    dropZone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropZone.classList.add('active');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('active');
    });

    dropZone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropZone.classList.remove('active');
        if (event.dataTransfer?.files?.length) {
            handleFiles(event.dataTransfer.files);
        }
    });

    input.addEventListener('change', (event) => {
        if (event.target.files?.length) {
            handleFiles(event.target.files);
        }
    });
})();
