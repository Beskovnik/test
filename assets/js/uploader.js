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

    function uploadFile(file) {
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.innerHTML = `
            <div><strong>${file.name}</strong></div>
            <div class="progress"><span></span></div>
            <div class="meta">0% · 0 KB/s · ETA --</div>
        `;
        list.appendChild(item);

        const progressBar = item.querySelector('.progress span');
        const meta = item.querySelector('.meta');
        const startTime = performance.now();

        const formData = new FormData();
        formData.append('csrf_token', csrfToken || '');
        formData.append('files[]', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/upload.php');
        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;
            const percent = Math.round((event.loaded / event.total) * 100);
            progressBar.style.width = `${percent}%`;
            const elapsed = (performance.now() - startTime) / 1000;
            const speed = event.loaded / Math.max(elapsed, 0.1);
            const remaining = event.total - event.loaded;
            const eta = remaining / Math.max(speed, 1);
            meta.textContent = `${percent}% · ${formatBytes(speed)}/s · ETA ${Math.round(eta)}s`;
        });
        xhr.onload = () => {
            if (xhr.status === 200) {
                meta.textContent = 'Končano';
            } else {
                meta.textContent = 'Napaka pri uploadu';
                item.classList.add('error');
            }
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
