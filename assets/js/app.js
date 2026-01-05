(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function request(url, options = {}) {
        const headers = options.headers || {};
        if (options.method && options.method.toUpperCase() === 'POST') {
            headers['X-Requested-With'] = 'XMLHttpRequest';
        }
        return fetch(url, { ...options, headers });
    }

    const cards = document.querySelectorAll('.card');
    if (cards.length) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = '<button class="prev">◀</button><div class="lightbox-content"></div><button class="next">▶</button>';
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
            if (event.target === lightbox) {
                lightbox.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!lightbox.classList.contains('active')) {
                return;
            }
            if (event.key === 'Escape') {
                lightbox.classList.remove('active');
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

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copy;
            try {
                await navigator.clipboard.writeText(text);
                button.textContent = 'Kopirano!';
                setTimeout(() => {
                    button.textContent = 'Deli';
                }, 1500);
            } catch (e) {
                window.location.href = text;
            }
        });
    });

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
