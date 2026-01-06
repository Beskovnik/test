<?php
require __DIR__ . '/app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::user();
$pdo = Database::connect();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$share = $_GET['s'] ?? null;

if ($id) {
    $stmt = $pdo->prepare('SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id WHERE posts.id = :id');
    $stmt->execute([':id' => $id]);
} elseif ($share) {
    $stmt = $pdo->prepare('SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id WHERE posts.share_token = :token');
    $stmt->execute([':token' => $share]);
} else {
    header('Location: /index.php');
    exit;
}

$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

// Access Check
if ($post['visibility'] !== 'public' && !$user) {
    header('Location: /login.php');
    exit;
}

// Increment Views (Async or Simple)
// For simplicity, we do it here, but ideally this is an async task
$pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = :id')->execute([':id' => $post['id']]);

// Like Status
$likeCount = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
$likeCount->execute([$post['id']]);
$likeCount = (int)$likeCount->fetchColumn();

$liked = false;
if ($user) {
    $checkLike = $pdo->prepare('SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?');
    $checkLike->execute([$post['id'], $user['id']]);
    $liked = (bool)$checkLike->fetchColumn();
}

// UI
require __DIR__ . '/includes/layout.php';

$title = $post['id'] . ' ' . ($post['type'] === 'video' ? 'video' : 'slika');
render_header($title, $user);

$previewSrc = !empty($post['preview_path']) ? '/' . $post['preview_path'] : '/' . $post['file_path'];
$originalSrc = '/' . $post['file_path'];
$thumbSrc = !empty($post['thumb_path']) ? '/' . $post['thumb_path'] : $previewSrc;

$mediaHtml = '';
if ($post['type'] === 'video') {
    $poster = htmlspecialchars($previewSrc);
    $src = htmlspecialchars($originalSrc);
    $mediaHtml = "<video src=\"{$src}\" poster=\"{$poster}\" controls autoplay muted loop></video>";
} else {
    $p = htmlspecialchars($previewSrc);
    $o = htmlspecialchars($originalSrc);
    // Click to load full res if different, or just zoom
    $mediaHtml = "<img src=\"{$p}\" data-original=\"{$o}\" alt=\"{$title}\" class=\"preview-image\" loading=\"lazy\" onclick=\"this.src=this.dataset.original; this.classList.remove('preview-image');\">";
}

$shareUrl = '/view.php?s=' . urlencode($post['share_token']); // Full URL needs host, logic in JS for copy
?>
<div class="view-page">
    <div class="media-panel">
        <?php echo $mediaHtml; ?>
    </div>
    <div class="info-panel">
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p class="meta">Objavil <?php echo htmlspecialchars($post['username'] ?? 'Anon'); ?></p>
        <?php if ($post['description']) : ?>
            <p><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
        <?php endif; ?>

        <div class="stats-bar">
            <span>👁️ <?php echo (int)$post['views'] + 1; ?></span>
            <span>📅 <?php echo date('d.m.Y', (int)$post['created_at']); ?></span>
        </div>

        <div class="actions">
            <button class="button like <?php echo $liked ? 'active' : ''; ?>" id="likeBtn" data-id="<?php echo $post['id']; ?>">
                <span class="like-icon"><?php echo $liked ? '❤️' : '🤍'; ?></span>
                <span class="like-label"><?php echo $liked ? 'Všečkano' : 'Všečkaj'; ?></span>
                <span class="like-count"><?php echo $likeCount; ?></span>
            </button>
            <button class="button ghost" onclick="sharePost('<?php echo $shareUrl; ?>')">Deli 🔗</button>
            <?php if ($user && ($user['role'] === 'admin' || $user['id'] === $post['user_id'])): ?>
                <button class="button danger" onclick="deletePost(<?php echo $post['id']; ?>)">Izbriši 🗑️</button>
            <?php endif; ?>
        </div>

        <section class="comments" id="commentsSection" data-id="<?php echo $post['id']; ?>">
            <h2>Komentarji</h2>
            <div class="comment-list" id="commentList">
                <!-- Loaded via JS -->
            </div>
            <?php if ($user) : ?>
                <form class="comment-form" id="commentForm">
                    <textarea name="body" rows="3" placeholder="Dodaj komentar..." required></textarea>
                    <button class="button small" type="submit">Objavi</button>
                </form>
            <?php else : ?>
                <p class="muted">Prijavi se za komentiranje.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
// Inline JS for View Actions (Refactor to app.js later if time permits)
async function sharePost(url) {
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
    try {
        const res = await fetch('/api/post_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id, csrf_token: document.querySelector('meta[name="csrf-token"]').content})
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

// Like Logic
document.getElementById('likeBtn')?.addEventListener('click', async function() {
    const id = this.dataset.id;
    try {
        const res = await fetch('/api/like.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({post_id: id, csrf_token: document.querySelector('meta[name="csrf-token"]').content})
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

// Comments Logic
const commentList = document.getElementById('commentList');
async function loadComments() {
    const id = document.getElementById('commentsSection').dataset.id;
    const res = await fetch(`/api/comment_list.php?post_id=${id}`);
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
}
loadComments();

document.getElementById('commentForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const body = this.body.value;
    const id = document.getElementById('commentsSection').dataset.id;
    const res = await fetch('/api/comment_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({post_id: id, body, csrf_token: document.querySelector('meta[name="csrf-token"]').content})
    });
    const data = await res.json();
    if (data.ok) {
        this.reset();
        loadComments();
    } else {
        showToast('error', data.error);
    }
});
</script>
<?php
render_footer();
