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
        // Determine Post ID either from comment section or like button data attribute
        const likeBtn = document.getElementById('likeBtn');
        const visibilitySelect = document.getElementById('visibilitySelect');
        const commentList = document.getElementById('commentList');
        const commentForm = document.getElementById('commentForm');
        const viewCountEl = document.getElementById('viewCount');
        const previewImage = document.querySelector('.media-panel .preview-image');

        let postId = null;
        if (commentsSection && commentsSection.dataset.id) {
            postId = commentsSection.dataset.id;
        } else if (likeBtn && likeBtn.dataset.id) {
            postId = likeBtn.dataset.id;
        }

        // If no postId found, we might not be on the view page or the DOM is missing ID
        if (!postId) return;

        // 3.1 View Count Increment & Display
        (async () => {
            // Only increment if we have a token (logged in) or maybe public?
            // The API requires CSRF for increment usually, but let's try.
            if (!csrfToken) return;
            try {
                const res = await fetch('/api/view.php', {
                    method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({post_id: postId})
                });
                const data = await res.json();
                if (data.ok && data.views && viewCountEl) {
                    viewCountEl.innerHTML = `<span class="material-icons" style="font-size:1rem;">visibility</span> ${data.views}`;
                }
            } catch (e) { console.error('View increment failed', e); }
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
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({post_id: postId})
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
                    } else {
                        showToast(data.error || 'Napaka', 'error');
                    }
                } catch (e) { showToast('Napaka omrežja', 'error'); }
            });
        }

        // 3.3 Visibility Toggle
        if (visibilitySelect) {
            visibilitySelect.addEventListener('change', async function() {
                try {
                    const res = await fetch('/api/media/visibility.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            media_id: postId,
                            visibility: this.value
                        })
                    });
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
        // Handle "Public Link Generation" separately if specific class exists
        const publicLinkBtn = document.getElementById('publicLinkBtn');
        if (publicLinkBtn) {
            publicLinkBtn.addEventListener('click', async () => {
                if (!postId) return;

                // Show loading state
                const originalText = publicLinkBtn.innerHTML;
                publicLinkBtn.disabled = true;
                publicLinkBtn.innerHTML = 'Generiranje...';

                try {
                    const res = await fetch('/api/media/generate_public_link.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({id: postId})
                    });
                    const data = await res.json();

                    if (data.success) {
                        // Update UI
                        const container = document.getElementById('publicLinkContainer');
                        if (container) {
                            container.style.display = 'block';
                            const input = container.querySelector('input');
                            if (input) input.value = data.public_url;
                        }
                        // Change status badge if exists
                        const badge = document.getElementById('statusBadge');
                        if (badge) {
                            badge.innerText = 'Javno';
                            badge.className = 'badge success'; // Assuming success class makes it green/visible
                        }

                        // Update visibility select if present
                        if (visibilitySelect) {
                            visibilitySelect.value = 'public'; // Sync logic
                        }

                        showToast('Javna povezava ustvarjena!');
                    } else {
                        showToast(data.error || 'Napaka pri generiranju', 'error');
                    }
                } catch (e) {
                    showToast('Napaka omrežja', 'error');
                } finally {
                    publicLinkBtn.disabled = false;
                    publicLinkBtn.innerHTML = originalText;
                }
            });
        }

        // Copy Public Link
        const copyPublicLinkBtn = document.getElementById('copyPublicLinkBtn');
        if (copyPublicLinkBtn) {
            copyPublicLinkBtn.addEventListener('click', async () => {
                const input = document.querySelector('#publicLinkContainer input');
                if (input && input.value) {
                    try {
                        await navigator.clipboard.writeText(input.value);
                        showToast('Javni URL kopiran!');
                    } catch (err) {
                        input.select();
                        document.execCommand('copy');
                        showToast('Javni URL kopiran (fallback)!');
                    }
                }
            });
        }

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
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({id: id})
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
                }
            });
        }

        // 3.7 Comments Logic
        async function loadComments() {
            if (!commentList) return;
            try {
                const res = await fetch(`/api/comment_list.php?post_id=${postId}`);
                const data = await res.json();

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
                            <div style="word-break: break-word; color: #ddd;">${comment.body}</div>
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

        if (commentList) {
            loadComments();
        }

        if (commentForm) {
            commentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!csrfToken) {
                    showToast('Niste prijavljeni.', 'error');
                    return;
                }
                const bodyInput = commentForm.querySelector('textarea');
                const body = bodyInput.value.trim();
                if (!body) return;

                try {
                    const res = await fetch('/api/comment_add.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({post_id: postId, body})
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

    // --- 4. Initialize ---
    document.addEventListener('DOMContentLoaded', () => {
        // Run View Page Logic if we are on view page
        if (document.querySelector('.view-page')) {
            initViewPage();
        }

        // Also init share modal helper if needed globally (e.g. for selection share)
        window.openShareModal = async function(mediaIds) {
             if (!mediaIds || mediaIds.length === 0) return;
             const title = prompt('Ime deljene zbirke (opcijsko):', 'Album ' + new Date().toLocaleDateString());
             if (title === null) return;

             try {
                const res = await fetch('/api/share/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
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
                    showToast(data.error, 'error');
                }
            } catch (e) {
                showToast('Napaka pri ustvarjanju delitve', 'error');
            }
        };
    });

})();
