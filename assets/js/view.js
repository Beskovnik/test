// View Page Logic
document.addEventListener('DOMContentLoaded', () => {
    // Utility for toasts
    const showToast = window.showToast || ((type, msg) => {
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

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';

    // We need a post ID for API calls.
    // We can get it from various elements.
    // Try to find it on commentsSection or one of the buttons.
    const commentsSection = document.getElementById('commentsSection');
    const likeBtn = document.getElementById('likeBtn');
    const deleteBtn = document.getElementById('deleteBtn');

    let postId = null;
    if (commentsSection) postId = commentsSection.dataset.id;
    else if (likeBtn) postId = likeBtn.dataset.id;
    else if (deleteBtn) postId = deleteBtn.dataset.id;

    // If no postId found, we might not be on the view page or structure is different
    if (!postId) return;

    // Async View Increment
    (async () => {
        try {
            const res = await fetch('/api/view.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({post_id: postId, csrf_token: csrfToken})
            });
            const data = await res.json();
            if (data.ok && data.views) {
                const viewCountEl = document.getElementById('viewCount');
                if (viewCountEl) {
                    // Update text node only, preserving the icon
                    const icon = viewCountEl.querySelector('.material-icons');
                    if (icon) {
                        // Reconstruct preserving icon
                        viewCountEl.innerHTML = '';
                        viewCountEl.appendChild(icon);
                        viewCountEl.appendChild(document.createTextNode(' ' + data.views));
                    } else {
                        // Fallback if icon missing
                        viewCountEl.textContent = '👁️ ' + data.views;
                    }
                }
            }
        } catch (e) {
            console.error('View increment failed', e);
        }
    })();

    // Share Button
    const shareBtn = document.getElementById('shareBtn');
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

    // Delete Button
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm('Res želiš izbrisati to objavo?')) return;
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

    // Comments
    const commentList = document.getElementById('commentList');
    const commentForm = document.getElementById('commentForm');

    async function loadComments() {
        if (!commentList) return;
        try {
            const res = await fetch(`/api/comment_list.php?post_id=${postId}`);
            const data = await res.json();
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
                    showToast('error', data.error);
                }
            } catch (e) {
                showToast('error', 'Napaka pri objavi komentarja');
            }
        });
    }
});
