document.addEventListener('DOMContentLoaded', () => {
    // View Increment
    const mediaPanel = document.querySelector('.media-panel'); // Anchor for finding context if needed, or just use page data
    // We need the post ID. It's usually in the URL or a data attribute.
    // In view.php, it's injected via PHP. We should use data attributes on a container.
    const container = document.querySelector('.view-page');
    if (!container) return;

    const postId = container.dataset.postId;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const viewCountEl = document.getElementById('viewCount');

    if (postId && csrfToken) {
        incrementView(postId, csrfToken, viewCountEl);
    }

    // Share
    window.sharePost = async function(url) {
        const fullUrl = window.location.origin + url;
        try {
            await navigator.clipboard.writeText(fullUrl);
            showToast('success', 'Povezava kopirana!');
        } catch (err) {
            prompt('Kopiraj povezavo:', fullUrl);
        }
    };

    // Delete
    window.deletePost = async function(id) {
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
                showToast('error', data.error || 'Napaka pri brisanju');
            }
        } catch (e) {
            showToast('error', 'Napaka omrežja');
        }
    };

    // Like Logic
    const likeBtn = document.getElementById('likeBtn');
    if (likeBtn) {
        likeBtn.addEventListener('click', async function() {
            const id = this.dataset.id;
            try {
                const res = await fetch('/api/like.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    this.classList.toggle('active', data.liked);
                    this.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
                    this.querySelector('.like-label').textContent = data.liked ? 'Všečkano' : 'Všečkaj';
                    this.querySelector('.like-count').textContent = data.count;
                }
            } catch (e) { console.error(e); }
        });
    }

    // Comments Logic
    const commentList = document.getElementById('commentList');
    const commentsSection = document.getElementById('commentsSection');
    const commentForm = document.getElementById('commentForm');

    if (commentsSection && commentList) {
        loadComments(commentsSection.dataset.id, commentList);

        if (commentForm) {
            commentForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const body = this.body.value;
                const id = commentsSection.dataset.id;
                const res = await fetch('/api/comment_add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({post_id: id, body, csrf_token: csrfToken})
                });
                const data = await res.json();
                if (data.ok) {
                    this.reset();
                    loadComments(id, commentList);
                } else {
                    showToast('error', data.error);
                }
            });
        }
    }
});

async function incrementView(id, token, displayElement) {
    try {
        const res = await fetch('/api/view.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({post_id: id, csrf_token: token})
        });
        const data = await res.json();
        if (data.ok && data.views && displayElement) {
            displayElement.textContent = '👁️ ' + data.views;
        }
    } catch (e) {
        console.error('View increment failed', e);
    }
}

async function loadComments(id, listElement) {
    const res = await fetch(`/api/comment_list.php?post_id=${id}`);
    const data = await res.json();
    if (data.ok) {
        listElement.innerHTML = data.comments.map(c => `
            <div class="comment">
                <strong>${c.author}</strong>
                <p>${c.body}</p>
                <small>${new Date(c.created_at * 1000).toLocaleString()}</small>
            </div>
        `).join('') || '<p class="muted">Ni komentarjev.</p>';
    }
}

// Toast Helper (if not globally available, though admin.js defines it, app.js doesn't seem to)
// Checking if showToast exists, if not defining a simple one.
if (typeof showToast === 'undefined') {
    window.showToast = function(type, message) {
        // Simple fallback
        const div = document.createElement('div');
        div.className = `flash ${type}`;
        div.style.position = 'fixed';
        div.style.top = '20px';
        div.style.right = '20px';
        div.style.zIndex = '9999';
        div.textContent = message;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }
}
