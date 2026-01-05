(function () {
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
        if (options.method && options.method.toUpperCase() === 'POST') {
            headers['X-Requested-With'] = 'XMLHttpRequest';
            const body = options.body;
            if (body instanceof FormData) {
               // FormData handles Content-Type
            } else {
               headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }
        return fetch(url, { ...options, headers });
    }

    // Viewer / Lightbox Logic
    const cards = document.querySelectorAll('.card');
    if (cards.length) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        // Complex structure for viewer + sidebar
        lightbox.innerHTML = `
            <div class="lightbox-overlay"></div>
            <button class="prev" aria-label="Previous">❮</button>
            <div class="lightbox-container">
                <div class="lightbox-media"></div>
                <div class="lightbox-sidebar">
                    <button class="close-sidebar" aria-label="Close">×</button>
                    <div class="meta-header">
                        <h2 class="meta-title"></h2>
                        <div class="meta-info">
                            <span class="meta-author"></span> • <span class="meta-date"></span>
                        </div>
                        <div class="meta-stats">
                            <span class="stat-views">👁️ <span class="val">0</span></span>
                            <button class="stat-like">❤️ <span class="val">0</span></button>
                        </div>
                    </div>
                    <div class="comments-section">
                        <h3>Komentarji</h3>
                        <div class="comments-list"></div>
                        <form class="comment-form">
                            <textarea name="body" placeholder="Dodaj komentar..." required></textarea>
                            <button type="submit" class="button small">Objavi</button>
                        </form>
                        <div class="login-cta" style="display:none;">
                            <a href="/login.php">Prijavi se</a> za komentiranje.
                        </div>
                    </div>
                </div>
            </div>
            <button class="next" aria-label="Next">❯</button>
        `;
        document.body.appendChild(lightbox);

        const container = lightbox.querySelector('.lightbox-container');
        const mediaContainer = lightbox.querySelector('.lightbox-media');
        const commentsList = lightbox.querySelector('.comments-list');
        const commentForm = lightbox.querySelector('.comment-form');
        const loginCta = lightbox.querySelector('.login-cta');
        const likeBtn = lightbox.querySelector('.stat-like');

        let currentIndex = -1;
        let currentItem = null;

        function formatDate(timestamp) {
            return new Date(timestamp * 1000).toLocaleDateString('sl-SI');
        }

        async function renderItem(index) {
            const items = window.galleryItems || [];
            if (!items[index]) {
                return;
            }
            currentIndex = index;
            currentItem = items[index];

            // UI Reset
            mediaContainer.innerHTML = '';
            commentsList.innerHTML = '<div class="loader">Nalaganje...</div>';

            // Media
            if (currentItem.type === 'video') {
                const video = document.createElement('video');
                video.src = currentItem.file;
                video.controls = true;
                video.autoplay = true;
                mediaContainer.appendChild(video);
            } else {
                const img = document.createElement('img');
                img.src = currentItem.file;
                img.alt = currentItem.title || '';
                mediaContainer.appendChild(img);
            }

            // Meta
            lightbox.querySelector('.meta-title').textContent = currentItem.title || 'Brez naslova';
            lightbox.querySelector('.meta-author').textContent = currentItem.username;
            lightbox.querySelector('.meta-date').textContent = formatDate(currentItem.created_at);
            lightbox.querySelector('.stat-views .val').textContent = currentItem.views;
            lightbox.querySelector('.stat-like .val').textContent = currentItem.likes;

            lightbox.classList.add('active');

            // Increment View
            const formData = new FormData();
            formData.append('post_id', currentItem.id);
            request('/api/view.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.views) {
                        currentItem.views = data.views;
                        lightbox.querySelector('.stat-views .val').textContent = data.views;
                    }
                })
                .catch(console.error);

            // Load Comments & Check Like status
            // Note: We need to know if user liked this post.
            // Currently API/like returns status after toggle. We might need a check endpoint or include in list.
            // For now, we assume state isn't known until interaction or extra fetch.
            // But we can check likes via comment_list call if we modify it, or separate call.
            // Simple approach: Just load comments for now. Like status is hard without extra API.
            // User requirement: "Prevent spam: same user cannot like multiple times". Backend handles this.
            // Frontend: button just toggles.

            loadComments(currentItem.id);
        }

        async function loadComments(postId) {
            try {
                const res = await request(`/api/comment_list.php?post_id=${postId}`);
                const data = await res.json();
                commentsList.innerHTML = '';
                if (data.comments && data.comments.length) {
                    data.comments.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'comment-item';
                        div.innerHTML = `<strong>${c.author}</strong>: ${c.body}`;
                        commentsList.appendChild(div);
                    });
                } else {
                    commentsList.innerHTML = '<p class="no-comments">Ni komentarjev.</p>';
                }
            } catch (e) {
                commentsList.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
            }
        }

        // Event Listeners (Delegation)
        const grid = document.querySelector('.grid');
        if (grid) {
            grid.addEventListener('click', (e) => {
                const card = e.target.closest('.card');
                if (card) {
                    const allCards = Array.from(document.querySelectorAll('.card'));
                    const index = allCards.indexOf(card);
                    if (index !== -1) renderItem(index);
                }
            });
        }

        // Navigation
        lightbox.querySelector('.prev').addEventListener('click', (e) => {
            e.stopPropagation();
            renderItem(Math.max(currentIndex - 1, 0));
        });
        lightbox.querySelector('.next').addEventListener('click', (e) => {
            e.stopPropagation();
            renderItem(Math.min(currentIndex + 1, cards.length - 1));
        });

        // Close
        const closeEvents = ['click', 'keydown'];
        lightbox.addEventListener('click', (e) => {
            if (e.target.classList.contains('lightbox-overlay') || e.target.classList.contains('close-sidebar')) {
                lightbox.classList.remove('active');
                mediaContainer.innerHTML = ''; // Stop video
            }
        });
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') {
                lightbox.classList.remove('active');
                mediaContainer.innerHTML = '';
            }
            if (e.key === 'ArrowLeft') renderItem(Math.max(currentIndex - 1, 0));
            if (e.key === 'ArrowRight') renderItem(Math.min(currentIndex + 1, cards.length - 1));
        });

        // Comments
        commentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!csrfToken) {
                // Not logged in
                loginCta.style.display = 'block';
                commentForm.style.display = 'none';
                return;
            }
            const body = commentForm.querySelector('textarea').value;
            const formData = new FormData();
            formData.append('post_id', currentItem.id);
            formData.append('body', body);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await request('/api/comment_add.php', { method: 'POST', body: formData });
                if (res.ok) {
                    commentForm.reset();
                    loadComments(currentItem.id);
                } else {
                    alert('Napaka pri objavi. Ste prijavljeni?');
                }
            } catch(e) { console.error(e); }
        });

        // Like
        likeBtn.addEventListener('click', async () => {
             if (!csrfToken) {
                alert('Za všečkanje se morate prijaviti.');
                return;
            }
            const formData = new FormData();
            formData.append('post_id', currentItem.id);
            formData.append('csrf_token', csrfToken);

            try {
                const res = await request('/api/like.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.like_count !== undefined) {
                    currentItem.likes = data.like_count;
                    likeBtn.querySelector('.val').textContent = data.like_count;
                    likeBtn.classList.toggle('liked', data.liked);
                }
            } catch(e) { console.error(e); }
        });

        // Check login state for form
        if (!csrfToken) {
             commentForm.style.display = 'none';
             loginCta.style.display = 'block';
        }
    }
})();
