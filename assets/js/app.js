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

    // View Page Logic (Static)
    const commentsSection = document.querySelector('.comments');
    const likeBtn = document.querySelector('.button.like');

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
                if (!csrfToken) return; // Should be handled by UI check

                const bodyInput = commentForm.querySelector('textarea');
                const body = bodyInput.value;
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('body', body);
                formData.append('csrf_token', csrfToken);

                try {
                    const res = await request('/api/comment_add.php', { method: 'POST', body: formData });
                    if (res.ok) {
                        bodyInput.value = '';
                        loadComments(postId);
                    } else {
                        alert('Napaka pri objavi.');
                    }
                } catch(e) { console.error(e); }
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
                        div.innerHTML = `<strong>${c.author}</strong>: ${c.body}`;
                        commentList.appendChild(div);
                    });
                } else {
                    commentList.innerHTML = '<p class="no-comments">Ni komentarjev.</p>';
                }
            } catch (e) {
                commentList.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
            }
        }
    }

    if (likeBtn) {
        likeBtn.addEventListener('click', async () => {
             if (!csrfToken) {
                alert('Za všečkanje se morate prijaviti.');
                return;
            }
            const postId = likeBtn.dataset.postId;
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
                    likeBtn.classList.toggle('liked', data.liked);
                }
            } catch(e) { console.error(e); }
        });
    }

})();
