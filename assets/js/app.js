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

    // Share Modal Logic
    window.openShareModal = async function(mediaIds) {
        if (!mediaIds || mediaIds.length === 0) return;

        const title = prompt('Ime deljene zbirke (opcijsko):', 'Album ' + new Date().toLocaleDateString());
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
                let modal = document.createElement('div');
                modal.className = 'modal open';
                modal.style.zIndex = '10000';
                modal.innerHTML = `
                    <div class="card" style="padding:2rem; max-width:500px; margin:auto; position:relative; top:20%;">
                        <h3 style="margin-top:0">Povezava ustvarjena!</h3>
                        <input type="text" value="${link}" readonly style="width:100%; padding:0.5rem; margin:1rem 0; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:white;">
                        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                            <button class="button" onclick="navigator.clipboard.writeText('${link}'); this.innerText='Kopirano!';">Kopiraj</button>
                            <button class="button ghost" onclick="this.closest('.modal').remove()">Zapri</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            } else {
                showToast('error', data.error);
            }
        } catch (e) {
            showToast('error', 'Napaka pri ustvarjanju delitve');
            console.error(e);
        }
    };

    // View Page Logic
    const viewPage = document.querySelector('.view-page');
    if (viewPage) {
        initViewPage();
    }

    function initViewPage() {
        const commentsSection = document.getElementById('commentsSection');
        const likeBtn = document.getElementById('likeBtn');
        const deleteBtns = document.querySelectorAll('#deleteBtn');
        const shareBtns = document.querySelectorAll('#shareBtn[data-url]');
        const commentList = document.getElementById('commentList');
        const commentForm = document.getElementById('commentForm');
        const viewCountEl = document.getElementById('viewCount');
        const visibilitySelect = document.getElementById('visibilitySelect');

        const postId = commentsSection?.dataset.id || likeBtn?.dataset.id || deleteBtns[0]?.dataset.id;
        if (!postId) return;

        const previewImage = document.querySelector('.media-panel .preview-image');
        if (previewImage) {
            previewImage.addEventListener('click', function() {
                if (this.dataset.original) {
                    this.src = this.dataset.original;
                    this.classList.remove('preview-image');
                }
            });
        }

        incrementViewCount(postId);

        shareBtns.forEach((btn) => {
            btn.addEventListener('click', () => sharePost(btn.dataset.url));
        });

        deleteBtns.forEach((btn) => {
            btn.addEventListener('click', () => deletePost(btn.dataset.id || postId));
        });

        if (likeBtn) {
            likeBtn.addEventListener('click', () => toggleLike(likeBtn, postId));
        }

        if (commentList) {
            loadComments();
        }

        if (commentForm) {
            commentForm.addEventListener('submit', submitComment);
        }

        if (visibilitySelect) {
            visibilitySelect.addEventListener('change', async function() {
                try {
                    const res = await fetch('/api/media/visibility.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            media_id: postId,
                            visibility: this.value,
                            csrf_token: csrfToken
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast('success', 'Vidnost posodobljena');
                    } else {
                        showToast('error', data.error || 'Napaka pri shranjevanju');
                    }
                } catch (e) {
                    showToast('error', 'Napaka pri shranjevanju');
                }
            });
        }

        async function incrementViewCount(id) {
            if (!csrfToken) return;
            try {
                const res = await fetch('/api/view.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok && data.views && viewCountEl) {
                    viewCountEl.textContent = '👁️ ' + data.views;
                }
            } catch (e) {
                console.error('View increment failed', e);
            }
        }

        async function sharePost(url) {
            if (!url) return;
            const fullUrl = window.location.origin + url;
            try {
                await navigator.clipboard.writeText(fullUrl);
                showToast('success', 'Povezava kopirana!');
            } catch (err) {
                prompt('Kopiraj povezavo:', fullUrl);
            }
        }

        async function deletePost(id) {
            if (!confirm('Res želiš izbrisati to objavo?')) return;
            if (!csrfToken) {
                showToast('error', 'Niste prijavljeni.');
                return;
            }
            try {
                const res = await fetch('/api/post_delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id, csrf_token: csrfToken})
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
        }

        async function toggleLike(btn, id) {
            if (!csrfToken) {
                showToast('error', 'Za všečkanje se morate prijaviti.');
                return;
            }
            try {
                const res = await fetch('/api/like.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    btn.classList.toggle('active', data.liked);
                    const icon = btn.querySelector('.like-icon');
                    const label = btn.querySelector('.like-label');
                    const count = btn.querySelector('.like-count');

                    if (icon) icon.textContent = data.liked ? '❤️' : '🤍';
                    if (label) label.textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                    if (count) count.textContent = data.count;
                } else if (data.error === 'CSRF Error') {
                    showToast('error', 'Napaka seje. Osveži stran.');
                } else {
                    showToast('error', data.error || 'Napaka');
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function loadComments() {
            try {
                const res = await fetch(`/api/comment_list.php?post_id=${postId}`);
                const data = await res.json();
                if (!commentList) return;
                commentList.innerHTML = '';
                if (data.ok && data.comments && data.comments.length) {
                    data.comments.forEach((comment) => {
                        const div = document.createElement('div');
                        div.className = 'comment-item';
                        div.style.marginBottom = '1rem';
                        const dateStr = formatDate(comment.created_at);
                        div.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.25rem;">
                                <strong>${comment.author}</strong>
                                <span style="font-size:0.8em; color:var(--muted);">${dateStr}</span>
                            </div>
                            <div style="word-break: break-word;">${comment.body}</div>
                        `;
                        commentList.appendChild(div);
                    });
                } else {
                    commentList.innerHTML = '<p class="no-comments" style="color:var(--muted);">Ni komentarjev.</p>';
                }
            } catch (e) {
                console.error(e);
                if (commentList) {
                    commentList.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
                }
            }
        }

        async function submitComment(event) {
            event.preventDefault();
            if (!csrfToken) {
                showToast('error', 'Niste prijavljeni.');
                return;
            }
            const bodyInput = commentForm.querySelector('textarea');
            const body = bodyInput.value.trim();
            if (!body) return;

            try {
                const res = await fetch('/api/comment_add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: postId, body, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    commentForm.reset();
                    loadComments();
                    showToast('success', 'Komentar objavljen.');
                } else {
                    showToast('error', data.error || 'Napaka pri objavi.');
                }
            } catch (e) {
                console.error(e);
                showToast('error', 'Napaka omrežja.');
            }
        }
    }

})();
