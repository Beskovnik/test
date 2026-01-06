// Debug Console Logic
(function() {
    // Only run if admin (we check by presence of a flag or element,
    // but the script itself is conditionally included by PHP)

    const MAX_LOGS = 50;

    // Create UI
    const container = document.createElement('div');
    container.id = 'debug-console';
    container.style.cssText = `
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 400px;
        height: 300px;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px;
        z-index: 9999;
        display: none; /* Hidden by default */
        flex-direction: column;
        font-family: monospace;
        font-size: 12px;
        color: #fff;
        box-shadow: 0 0 20px rgba(0,0,0,0.5);
    `;

    const header = document.createElement('div');
    header.style.cssText = `
        padding: 8px;
        background: rgba(255,255,255,0.1);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
    `;
    header.innerHTML = '<span>🔧 Debug Console</span>';

    const controls = document.createElement('div');

    const clearBtn = document.createElement('button');
    clearBtn.textContent = 'Clear';
    clearBtn.onclick = () => logContent.innerHTML = '';

    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.style.marginLeft = '10px';
    closeBtn.onclick = () => container.style.display = 'none';

    controls.appendChild(clearBtn);
    controls.appendChild(closeBtn);
    header.appendChild(controls);

    const logContent = document.createElement('div');
    logContent.style.cssText = `
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    `;

    container.appendChild(header);
    container.appendChild(logContent);
    document.body.appendChild(container);

    // Toggle Button (Visible always for admin)
    const toggleBtn = document.createElement('div');
    toggleBtn.textContent = '🐛';
    toggleBtn.style.cssText = `
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 30px;
        height: 30px;
        background: #333;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 9998;
        opacity: 0.5;
    `;
    toggleBtn.onmouseover = () => toggleBtn.style.opacity = '1';
    toggleBtn.onmouseout = () => toggleBtn.style.opacity = '0.5';
    toggleBtn.onclick = () => {
        container.style.display = 'flex';
    };
    document.body.appendChild(toggleBtn);

    // Logger function
    function addLog(type, message, details = null) {
        const row = document.createElement('div');
        row.style.marginBottom = '4px';
        row.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
        row.style.paddingBottom = '4px';

        const time = new Date().toLocaleTimeString();
        let color = '#fff';
        if (type === 'ERROR') color = '#ff5555';
        if (type === 'WARN') color = '#ffb86c';
        if (type === 'SUCCESS') color = '#50fa7b';
        if (type === 'INFO') color = '#8be9fd';

        let html = `<span style="color:#6272a4">[${time}]</span> <strong style="color:${color}">${type}</strong> ${message}`;

        if (details) {
            try {
                const detailStr = typeof details === 'object' ? JSON.stringify(details, null, 2) : details;
                html += `<pre style="margin:4px 0 0 10px; color:#f8f8f2; overflow-x:auto; background:rgba(255,255,255,0.05); padding:4px;">${detailStr.substring(0, 2000)}</pre>`;
            } catch (e) {
                html += ` [Details Error]`;
            }
        }

        row.innerHTML = html;
        logContent.appendChild(row);
        logContent.scrollTop = logContent.scrollHeight;

        // Prune
        while (logContent.children.length > MAX_LOGS) {
            logContent.removeChild(logContent.firstChild);
        }
    }

    // Intercept Fetch
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        const url = args[0];
        addLog('INFO', `FETCH ${url}`);
        try {
            const response = await originalFetch.apply(this, args);
            const clone = response.clone();

            clone.text().then(text => {
                let isJson = false;
                let data = text;
                try {
                    data = JSON.parse(text);
                    isJson = true;
                } catch(e) {}

                if (!response.ok) {
                    addLog('ERROR', `FETCH FAIL ${response.status} ${url}`, data);
                } else if (url.includes('upload.php')) {
                    // Specific log for upload
                    addLog('SUCCESS', `UPLOAD RESPONSE`, data);
                }
            });

            return response;
        } catch (error) {
            addLog('ERROR', `FETCH NET_ERR ${url}`, error.message);
            throw error;
        }
    };

    // Intercept XHR
    const originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url) {
        this._url = url;
        originalOpen.apply(this, arguments);
    };

    const originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function() {
        this.addEventListener('load', () => {
            let data = this.responseText;
            try {
                data = JSON.parse(this.responseText);
            } catch (e) {}

            if (this.status >= 400) {
                addLog('ERROR', `XHR FAIL ${this.status} ${this._url}`, data);
            } else if (this._url.includes('upload.php')) {
                 addLog('SUCCESS', `UPLOAD XHR ${this.status}`, data);
            }
        });
        this.addEventListener('error', () => {
            addLog('ERROR', `XHR NET_ERR ${this._url}`);
        });
        originalSend.apply(this, arguments);
    };

    // Global Errors
    window.onerror = function(msg, url, line) {
        addLog('ERROR', `JS Error: ${msg} (${url}:${line})`);
    };

    window.addEventListener('unhandledrejection', function(event) {
        addLog('ERROR', `Unhandled Promise: ${event.reason}`);
    });

    addLog('INFO', 'Debug Console Initialized');

})();
