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
    // CSRF Helper
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';

    // Toast Notification Helper
    window.showToast = window.showToast || ((type, msg) => {
        console.log(`[${type}] ${msg}`);
        let toast = document.getElementById('toast-container');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-container';
            toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;';
            document.body.appendChild(toast);
        }
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.style.cssText = 'background:rgba(0,0,0,0.8);color:#fff;padding:10px;margin-top:5px;border-radius:4px;';
        el.textContent = msg;
        toast.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    });

    // Share Modal Logic
    window.openShareModal = async function(mediaIds) {
        if (!mediaIds || mediaIds.length === 0) return;

        const title = prompt("Ime deljene zbirke (opcijsko):", "Album " + new Date().toLocaleDateString());
        if (title === null) return; // cancelled

        try {
            const res = await fetch('/api/share/create.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    title: title,
                    media_ids: mediaIds
                })
            });
            const data = await res.json();

            if (data.success) {
                const link = window.location.origin + data.share_url;
                // Show modal with link
                let m = document.createElement('div');
                m.className = 'modal open';
                m.style.zIndex = '10000';
                m.innerHTML = `
                    <div class="card" style="padding:2rem; max-width:500px; margin:auto; position:relative; top:20%;">
                        <h3 style="margin-top:0">Povezava ustvarjena!</h3>
                        <input type="text" value="${link}" readonly style="width:100%; padding:0.5rem; margin:1rem 0; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:white;">
                        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                            <button class="button" onclick="navigator.clipboard.writeText('${link}'); this.innerText='Kopirano!';">Kopiraj</button>
                            <button class="button ghost" onclick="this.closest('.modal').remove()">Zapri</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(m);
            } else {
                window.showToast('error', data.error);
            }
        } catch (e) {
            window.showToast('error', 'Napaka pri ustvarjanju delitve');
            console.error(e);
        }
    };

    // View Page Logic
    document.addEventListener('DOMContentLoaded', () => {
        // Elements
        const commentsSection = document.getElementById('commentsSection');
        const likeBtn = document.getElementById('likeBtn');
        const deleteBtn = document.getElementById('deleteBtn');
        const shareBtn = document.getElementById('shareBtn');
        const commentList = document.getElementById('commentList');
        const commentForm = document.getElementById('commentForm');
        const viewCountEl = document.getElementById('viewCount');

        // Determine Post ID
        let postId = null;
        if (commentsSection) postId = commentsSection.dataset.id;
        else if (likeBtn) postId = likeBtn.dataset.id;
        else if (deleteBtn) postId = deleteBtn.dataset.id;

        // Exit if not on view page (no postId found)
        if (!postId) return;

        // Visibility Toggle Logic
        const visibilitySelect = document.getElementById('visibilitySelect');
        if (visibilitySelect) {
            visibilitySelect.addEventListener('change', async function() {
                const newVal = this.value;
                try {
                    const res = await fetch('/api/media/visibility.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            media_id: postId,
                            visibility: newVal,
                            csrf_token: csrfToken
                        })
                    });
                    const data = await res.json();
                    if(data.success) {
                        window.showToast('success', 'Vidnost posodobljena');
                    } else {
                        window.showToast('error', data.error);
                        // Revert?
                    }
                } catch(e) {
                    window.showToast('error', 'Napaka pri shranjevanju');
                }
            });
        }

        // Async View Increment
        (async () => {
            try {
                const res = await fetch('/api/view.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: postId, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok && data.views && viewCountEl) {
                    viewCountEl.textContent = '👁️ ' + data.views;
                }
            } catch (e) {
                console.error('View increment failed', e);
            }
        })();

        // Share Button
        if (shareBtn) {
            shareBtn.addEventListener('click', async () => {
                const url = shareBtn.dataset.url;
                const fullUrl = window.location.origin + url;
                try {
                    await navigator.clipboard.writeText(fullUrl);
                    window.showToast('success', 'Povezava kopirana!');
                } catch (err) {
                    prompt('Kopiraj povezavo:', fullUrl);
                }
            });
        }

        // Delete Button
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (!confirm('Res želiš izbrisati to objavo?')) return;
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
                    const res = await fetch('/api/post_delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: postId, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        window.location.href = '/index.php';
                    } else {
                        window.showToast('error', data.error || 'Napaka pri brisanju');
                    }
                } catch (e) {
                    window.showToast('error', 'Napaka omrežja');
                }
            });
        }

        // Like Button
        if (likeBtn) {
            likeBtn.addEventListener('click', async () => {
                try {
                    const res = await fetch('/api/like.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({post_id: postId, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        likeBtn.classList.toggle('active', data.liked);
                        const icon = likeBtn.querySelector('.like-icon');
                        const label = likeBtn.querySelector('.like-label');
                        const count = likeBtn.querySelector('.like-count');

                        if(icon) icon.textContent = data.liked ? '❤️' : '🤍';
                        if(label) label.textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                        if(count) count.textContent = data.count;
                    }
                } catch (e) { console.error(e); }
            });
        }

        // Comments Logic
        async function loadComments() {
            if (!commentList) return;
            try {
                const res = await fetch(`/api/comment_list.php?post_id=${postId}`);
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
                if (data.ok) {
                    commentList.innerHTML = data.comments.map(c => `
                        <div class="comment">
                            <strong>${c.author}</strong>
                            <p>${c.body}</p>
                            <small>${new Date(c.created_at * 1000).toLocaleString()}</small>
                        </div>
                    `).join('') || '<p class="muted">Ni komentarjev.</p>';
                }
            } catch (e) {
                console.error(e);
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
        if (commentList) loadComments();

        if (commentForm) {
            commentForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const body = this.body.value;
                try {
                    const res = await fetch('/api/comment_add.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({post_id: postId, body, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.reset();
                        loadComments();
                    } else {
                        window.showToast('error', data.error);
                    }
                } catch (e) {
                    window.showToast('error', 'Napaka pri objavi komentarja');
                }
            });
        }
    });

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
