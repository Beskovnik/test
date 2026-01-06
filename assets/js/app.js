(function () {
    // --- 1. Utilities ---

    // CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Toast Notification
    window.showToast = function(message, type = 'success') {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
            document.body.appendChild(toastContainer);
        }

        const el = document.createElement('div');
        const isError = type === 'error';
        const borderColor = isError ? 'var(--danger, #e74c3c)' : 'var(--success, #2ecc71)';

        el.className = 'toast';
        el.style.cssText = `
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            background: var(--panel, #2a2a2a);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border, #444);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid ${borderColor};
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            pointer-events: auto;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        `;

        el.innerHTML = `<span style="font-weight:600;">${message}</span>`;
        toastContainer.appendChild(el);

        // Animate In
        requestAnimationFrame(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });

        // Remove after 3s
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 300);
        }, 3000);
    };

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

    // --- 2. Global UI Logic ---

    // Theme Toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        const html = document.documentElement;
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            themeToggle.textContent = newTheme === 'light' ? '☀️' : '🌙';
        });
        // Init icon
        const stored = localStorage.getItem('theme');
        if (stored) themeToggle.textContent = stored === 'light' ? '☀️' : '🌙';
    }

    // UI Auto Scale
    function updateUIScale() {
        const width = window.innerWidth;
        let scale = 1.0;
        if (width < 420) scale = 0.92;
        else if (width < 768) scale = 0.96;
        else if (width < 1200) scale = 1.0;
        else if (width < 1600) scale = 1.02;
        else scale = 1.04;
        document.documentElement.style.setProperty('--ui-auto-scale', scale);
    }
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateUIScale, 150);
    });
    updateUIScale();

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

    // --- 3. View Page Logic ---

    function initViewPage() {
        // Elements
        const commentsSection = document.getElementById('commentsSection');
        if (!commentsSection) return; // Not on view page

        const postId = commentsSection.dataset.id;
        const likeBtn = document.getElementById('likeBtn');
        const visibilitySelect = document.getElementById('visibilitySelect');
        const commentList = document.getElementById('commentList');
        const commentForm = document.getElementById('commentForm');
        const viewCountEl = document.getElementById('viewCount');
        const previewImage = document.querySelector('.media-panel .preview-image');

        // 3.1 View Count
        (async () => {
            try {
                const res = await fetch('/api/view.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: postId, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok && data.views && viewCountEl) {
                    viewCountEl.innerHTML = `<span class="material-icons" style="font-size:1rem;">visibility</span> ${data.views}`;
                }
            } catch (e) { console.error(e); }
        })();

        // 3.2 Like Button
        if (likeBtn) {
            likeBtn.addEventListener('click', async () => {
                if (!csrfToken) {
                    showToast('Za všečkanje se morate prijaviti.', 'error');
                    return;
                }
                try {
                    const res = await fetch('/api/like.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({post_id: postId, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        likeBtn.classList.toggle('active', data.liked);
                        likeBtn.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
                        likeBtn.querySelector('.like-label').textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                        likeBtn.querySelector('.like-count').textContent = data.count;
                    } else {
                        showToast(data.error || 'Napaka', 'error');
                    }
                } catch (e) { showToast('Napaka omrežja', 'error'); }
            });
        }

        // 3.3 Visibility Toggle
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
                    // Some endpoints return {success:true} others {ok:true}. Check both.
                    const data = await res.json();
                    if(data.success || data.ok) {
                        showToast('Vidnost posodobljena');
                    } else {
                        showToast(data.error || 'Napaka', 'error');
                    }
                } catch(e) {
                    showToast('Napaka pri shranjevanju', 'error');
                }
            });
        }

        // 3.4 Share Buttons (Delegate)
        const shareBtns = document.querySelectorAll('.js-share-btn');
        shareBtns.forEach(btn => {
            btn.addEventListener('click', async () => {
                const url = btn.dataset.url; // Relative URL
                if (!url) return;
                const fullUrl = window.location.origin + url;
                try {
                    await navigator.clipboard.writeText(fullUrl);
                    showToast('Povezava kopirana!');
                } catch (err) {
                    prompt('Kopiraj povezavo:', fullUrl);
                }
            });
        });

        // 3.5 Delete Buttons (Delegate)
        const deleteBtns = document.querySelectorAll('.js-delete-btn');
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Res želiš izbrisati to objavo?')) return;
                const id = btn.dataset.id;
                try {
                    const res = await fetch('/api/post_delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: id, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        window.location.href = '/index.php';
                    } else {
                        showToast(data.error || 'Napaka pri brisanju', 'error');
                    }
                } catch (e) {
                    showToast('Napaka omrežja', 'error');
                }
            });
        });

        // 3.6 Image Preview Click
        if (previewImage) {
            previewImage.addEventListener('click', function() {
                if (this.dataset.original) {
                    this.src = this.dataset.original;
                    this.classList.remove('preview-image');
                    // Optional: remove onclick to prevent reload or toggle back?
                    // For now, keep as is (load full res).
                }
            });
        }

        // 3.7 Comments
        async function loadComments() {
            if (!commentList) return;
            try {
                const res = await fetch(`/api/comment_list.php?post_id=${postId}`);
                const data = await res.json();
                commentList.innerHTML = '';
                if (data.ok && data.comments && data.comments.length) {
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
                            <div style="word-break: break-word; color: #ddd;">${c.body}</div>
                        `;
                        commentList.appendChild(div);
                    });
                } else {
                    commentList.innerHTML = '<p class="no-comments" style="color:var(--muted);">Ni komentarjev.</p>';
                }
            } catch (e) {
                console.error(e);
                commentList.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
            }
        }

        loadComments(); // Initial load

        if (commentForm) {
            commentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!csrfToken) {
                    showToast('Niste prijavljeni.', 'error');
                    return;
                }
                const bodyInput = commentForm.querySelector('textarea');
                const body = bodyInput.value;

                try {
                    const res = await fetch('/api/comment_add.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({post_id: postId, body, csrf_token: csrfToken})
                    });
                    const data = await res.json();
                    if (data.ok) {
                        bodyInput.value = '';
                        loadComments();
                        showToast('Komentar objavljen.');
                    } else {
                        showToast(data.error || 'Napaka pri objavi.', 'error');
                    }
                } catch(e) {
                    showToast('Napaka omrežja.', 'error');
                }
            });
        }
    }

    // Initialize View Page logic on load
    document.addEventListener('DOMContentLoaded', initViewPage);

})();
