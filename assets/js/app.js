(function () {
    // Toast Utility
    const showToast = window.showToast || ((type, msg) => {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(toastContainer);
        }

        const el = document.createElement('div');
        // Re-use existing toast styles from CSS if possible, but structure manually to be safe
        el.className = `toast show`; // CSS handles animation
        // We manually inject styles to match the 'toast-body' structure in layout.php or just use simple style
        // Let's use the structure from layout.php for consistency
        const isError = type === 'error';
        const color = isError ? 'var(--danger)' : 'var(--success)';

        el.innerHTML = `
            <div class="toast-body ${isError ? 'error-toast' : 'success-toast'}" style="padding: 1rem 1.5rem; border-radius: 1rem; background: var(--panel); backdrop-filter: blur(16px); border: 1px solid var(--border); color: #fff; display:flex; align-items:center; gap:0.75rem; border-left: 4px solid ${color};">
                <span style="font-weight:600;">${msg}</span>
            </div>
        `;

        toastContainer.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, 3000);
    });
    window.showToast = showToast;

    // Theme Management
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const storedTheme = localStorage.getItem('theme');

    if (storedTheme) {
        html.setAttribute('data-theme', storedTheme);
        updateThemeIcon(storedTheme);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (themeToggle) {
            themeToggle.textContent = theme === 'light' ? '☀️' : '🌙';
        }
    }

    // UI Auto Scale
    function updateUIScale() {
        const width = window.innerWidth;
        let scale = 1.0;

        if (width < 420) {
            scale = 0.92;
        } else if (width < 768) {
            scale = 0.96;
        } else if (width < 1200) {
            scale = 1.0;
        } else if (width < 1600) {
            scale = 1.02;
        } else {
            scale = 1.04;
        }

        document.documentElement.style.setProperty('--ui-auto-scale', scale);
    }

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateUIScale, 150);
    });
    updateUIScale(); // Init

    // Mobile Menu
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');

    if (menuToggle && sidebar && overlay) {
        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    }

    // CSRF & Request Helper
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function request(url, options = {}) {
        const headers = options.headers || {};
        const method = options.method ? options.method.toUpperCase() : 'GET';

        if (method === 'POST') {
            headers['X-Requested-With'] = 'XMLHttpRequest';

            if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            } else if (options.body instanceof FormData) {
                // Let browser set Content-Type
            } else if (!headers['Content-Type']) {
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }
        return fetch(url, { ...options, headers });
    }

    // Date Formatter
    function formatDate(isoString) {
        if (!isoString) return '—';
        try {
            const date = new Date(isoString);
            if (isNaN(date.getTime())) return '—';

            return new Intl.DateTimeFormat('sl-SI', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            }).format(date);
        } catch (e) {
            return '—';
        }
    }

    // View Page Logic (Static)
    const commentsSection = document.querySelector('.comments');
    const likeBtn = document.querySelector('.button.like');
    const deleteBtn = document.getElementById('deleteBtn');
    const shareBtn = document.getElementById('shareBtn');

    if (commentsSection) {
        const postId = commentsSection.dataset.postId;
        const commentList = commentsSection.querySelector('.comment-list');
        const commentForm = commentsSection.querySelector('.comment-form');

        // Load Comments
        loadComments(postId);

        // Submit Comment
        if (commentForm) {
            commentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!csrfToken) {
                    showToast('error', 'Niste prijavljeni.');
                    return;
                }

                const bodyInput = commentForm.querySelector('textarea');
                const body = bodyInput.value;

                const payload = {
                    post_id: postId,
                    body: body,
                    csrf_token: csrfToken
                };

                try {
                    const res = await request('/api/comment_add.php', { method: 'POST', body: payload });
                    const json = await res.json();

                    if (res.ok && json.ok) {
                        bodyInput.value = '';
                        loadComments(postId);
                        showToast('success', 'Komentar objavljen.');
                    } else {
                        showToast('error', json.error || 'Napaka pri objavi.');
                    }
                } catch(e) { console.error(e); showToast('error', 'Napaka omrežja.'); }
            });
        }

        async function loadComments(id) {
            try {
                const res = await request(`/api/comment_list.php?post_id=${id}`);
                const data = await res.json();
                commentList.innerHTML = '';
                if (data.comments && data.comments.length) {
                    data.comments.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'comment-item';
                        div.style.marginBottom = '1rem';
                        const dateStr = formatDate(c.created_at);
                        div.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.25rem;">
                                <strong>${c.author}</strong>
                                <span style="font-size:0.8em; color:var(--muted);">${dateStr}</span>
                            </div>
                            <div style="word-break: break-word;">${c.body}</div>
                        `;
                        commentList.appendChild(div);
                    });
                } else {
                    commentList.innerHTML = '<p class="no-comments" style="color:var(--muted);">Ni komentarjev.</p>';
                }
            } catch (e) {
                commentList.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
            }
        }
    }

    if (likeBtn) {
        likeBtn.addEventListener('click', async () => {
             if (!csrfToken) {
                showToast('error', 'Za všečkanje se morate prijaviti.');
                return;
            }
            const postId = likeBtn.dataset.id || likeBtn.dataset.postId;
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await request('/api/like.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.like_count !== undefined) {
                    likeBtn.querySelector('.like-count').textContent = data.like_count;
                    const label = likeBtn.querySelector('.like-label');
                    if (label) label.textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                    likeBtn.classList.toggle('active', data.liked);
                    // Also update icon if it exists
                    const icon = likeBtn.querySelector('.like-icon');
                    if (icon) icon.textContent = data.liked ? '❤️' : '🤍';
                }
            } catch(e) { console.error(e); }
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm('Res želiš izbrisati to objavo?')) return;
            const postId = deleteBtn.dataset.id;
            try {
                const res = await fetch('/api/post_delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: postId, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    window.location.href = '/index.php';
                } else {
                    showToast('error', data.error || 'Napaka pri brisanju');
                }
            } catch (e) {
                showToast('error', 'Napaka omrežja');
            }
        });
    }

    if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
            const url = shareBtn.dataset.url;
            const fullUrl = window.location.origin + url;
            try {
                await navigator.clipboard.writeText(fullUrl);
                showToast('success', 'Povezava kopirana!');
            } catch (err) {
                prompt('Kopiraj povezavo:', fullUrl);
            }
        });
    }

})();
