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
        }
        return fetch(url, { ...options, headers });
    }

    // Lightbox Logic
    const cards = document.querySelectorAll('.card');
    if (cards.length) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = '<button class="prev" aria-label="Previous">❮</button><div class="lightbox-content"></div><button class="next" aria-label="Next">❯</button><button class="close" aria-label="Close" style="position:absolute;top:20px;right:20px;background:none;border:none;color:#fff;font-size:30px;cursor:pointer;">×</button>';
        document.body.appendChild(lightbox);
        const content = lightbox.querySelector('.lightbox-content');
        let currentIndex = -1;

        function renderItem(index) {
            const items = window.galleryItems || [];
            if (!items[index]) {
                return;
            }
            currentIndex = index;
            const item = items[index];
            content.innerHTML = '';
            if (item.type === 'video') {
                const video = document.createElement('video');
                video.src = item.file;
                video.controls = true;
                video.autoplay = true;
                content.appendChild(video);
            } else {
                const img = document.createElement('img');
                img.src = item.file;
                img.alt = item.title || '';
                content.appendChild(img);
            }
            lightbox.classList.add('active');
        }

        cards.forEach((card, index) => {
            card.addEventListener('click', () => renderItem(index));
        });

        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox || event.target.closest('.close')) {
                lightbox.classList.remove('active');
                content.innerHTML = ''; // Stop video
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!lightbox.classList.contains('active')) {
                return;
            }
            if (event.key === 'Escape') {
                lightbox.classList.remove('active');
                content.innerHTML = '';
            }
            if (event.key === 'ArrowRight') {
                renderItem(Math.min(currentIndex + 1, cards.length - 1));
            }
            if (event.key === 'ArrowLeft') {
                renderItem(Math.max(currentIndex - 1, 0));
            }
        });

        lightbox.querySelector('.prev').addEventListener('click', (event) => {
            event.stopPropagation();
            renderItem(Math.max(currentIndex - 1, 0));
        });

        lightbox.querySelector('.next').addEventListener('click', (event) => {
            event.stopPropagation();
            renderItem(Math.min(currentIndex + 1, cards.length - 1));
        });
    }

    // Like Button
    const likeButton = document.querySelector('.button.like');
    if (likeButton) {
        likeButton.addEventListener('click', async () => {
            const postId = likeButton.dataset.postId;
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('csrf_token', csrfToken || '');
            const response = await request('/api/like.php', {
                method: 'POST',
                body: formData,
            });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            likeButton.querySelector('.like-count').textContent = data.like_count;
            likeButton.querySelector('.like-label').textContent = data.liked ? 'Všečkano' : 'Všečkaj';
        });
    }

    // Copy Button
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copy;
            try {
                await navigator.clipboard.writeText(text);
                const original = button.textContent;
                button.textContent = 'Kopirano!';
                setTimeout(() => {
                    button.textContent = original;
                }, 1500);
            } catch (e) {
                window.location.href = text;
            }
        });
    });

    // Comments
    const commentsSection = document.querySelector('.comments');
    if (commentsSection) {
        const list = commentsSection.querySelector('.comment-list');
        const postId = commentsSection.dataset.postId;

        async function loadComments() {
            const response = await request(`/api/comment_list.php?post_id=${postId}`);
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            list.innerHTML = '';
            data.comments.forEach((comment) => {
                const item = document.createElement('div');
                item.className = 'comment';
                item.innerHTML = `<strong>${comment.author}</strong><p>${comment.body}</p>`;
                list.appendChild(item);
            });
        }

        loadComments();

        const form = commentsSection.querySelector('.comment-form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const body = form.querySelector('textarea').value.trim();
                if (!body) {
                    return;
                }
                const formData = new FormData(form);
                formData.append('post_id', postId);
                const response = await request('/api/comment_add.php', {
                    method: 'POST',
                    body: formData,
                });
                if (response.ok) {
                    form.reset();
                    loadComments();
                }
            });
        }
    }
})();
