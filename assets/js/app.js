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

    // Global Toast Function
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => toast.classList.add('show'));

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // View Page Logic
    const viewPage = document.querySelector('.view-page');
    if (viewPage) {
        initViewPage();
    }

    function initViewPage() {
        const commentsSection = document.getElementById('commentsSection');
        if (!commentsSection) return;

        const postId = commentsSection.dataset.id;
        const commentList = document.getElementById('commentList');
        const commentForm = document.getElementById('commentForm');
        const likeBtn = document.getElementById('likeBtn');
        const viewCount = document.getElementById('viewCount');
        const shareBtn = document.getElementById('shareBtn');
        const deleteBtn = document.getElementById('deleteBtn');

        // Increment View
        incrementViewCount(postId);

        // Share
        if (shareBtn) {
            shareBtn.addEventListener('click', () => sharePost(shareBtn.dataset.url));
        }

        // Delete
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => deletePost(deleteBtn.dataset.id));
        }

        // Like
        if (likeBtn) {
            likeBtn.addEventListener('click', () => toggleLike(likeBtn, postId));
        }

        // Image Preview Click
        const previewImage = document.querySelector('.media-panel .preview-image');
        if (previewImage) {
            previewImage.addEventListener('click', function() {
                if (this.dataset.original) {
                    this.src = this.dataset.original;
                    this.classList.remove('preview-image');
                }
            });
        }

        // Comments
        loadComments(postId, commentList);
        if (commentForm) {
            commentForm.addEventListener('submit', (e) => submitComment(e, postId, commentList, commentForm));
        }

        async function incrementViewCount(id) {
            try {
                const res = await fetch('/api/view.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok && data.views && viewCount) {
                    viewCount.textContent = '👁️ ' + data.views;
                }
            } catch (e) {
                console.error('View increment failed', e);
            }
        }

        async function sharePost(url) {
            const fullUrl = window.location.origin + url;
            try {
                await navigator.clipboard.writeText(fullUrl);
                showToast('Povezava kopirana!', 'success');
            } catch (err) {
                prompt('Kopiraj povezavo:', fullUrl);
            }
        }

        async function deletePost(id) {
            if (!confirm('Res želiš izbrisati to objavo?')) return;
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
                    showToast(data.error || 'Napaka pri brisanju', 'error');
                }
            } catch (e) {
                showToast('Napaka omrežja', 'error');
            }
        }

        async function toggleLike(btn, id) {
            try {
                const res = await fetch('/api/like.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    btn.classList.toggle('active', data.liked);
                    btn.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
                    btn.querySelector('.like-label').textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                    btn.querySelector('.like-count').textContent = data.count;
                } else if (data.error === 'CSRF Error') {
                   showToast('Napaka seje. Osveži stran.', 'error');
                } else {
                   showToast(data.error || 'Napaka', 'error');
                }
            } catch (e) { console.error(e); }
        }

        async function loadComments(id, list) {
            try {
                const res = await fetch(`/api/comment_list.php?post_id=${id}`);
                const data = await res.json();
                if (data.ok) {
                    list.innerHTML = '';
                    if (data.comments && data.comments.length) {
                        data.comments.forEach(c => {
                            const div = document.createElement('div');
                            div.className = 'comment';

                            const strong = document.createElement('strong');
                            strong.textContent = c.author;
                            div.appendChild(strong);

                            const p = document.createElement('p');
                            p.textContent = c.body;
                            div.appendChild(p);

                            const small = document.createElement('small');
                            small.textContent = new Date(c.created_at * 1000).toLocaleString();
                            div.appendChild(small);

                            list.appendChild(div);
                        });
                    } else {
                        list.innerHTML = '<p class="muted">Ni komentarjev.</p>';
                    }
                }
            } catch(e) {
                list.innerHTML = '<p class="error">Napaka pri nalaganju.</p>';
            }
        }

        async function submitComment(e, id, list, form) {
            e.preventDefault();
            const body = form.body.value;
            try {
                const res = await fetch('/api/comment_add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, body, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    form.reset();
                    loadComments(id, list);
                } else {
                    showToast(data.error || 'Napaka', 'error');
                }
            } catch(e) {
                showToast('Napaka omrežja', 'error');
            }
        }
    }

})();
