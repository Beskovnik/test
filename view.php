<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php';

use App\Auth;
use App\Database;

$share = $_GET['s'] ?? null;
$user = Auth::user();
$pdo = Database::connect();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
    $stmt = $pdo->prepare('SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id WHERE posts.id = :id');
    $stmt->execute([':id' => $id]);
} elseif ($share) {
    $stmt = $pdo->prepare('SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id WHERE posts.share_token = :token');
    $stmt->execute([':token' => $share]);
} else {
    redirect('/index.php');
}

$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo '<div style="color:white;text-align:center;padding:5rem;">Vsebina ni najdena.</div>';
    exit;
}

// ACCESS CONTROL
$isOwner = $user && ($post['user_id'] == $user['id'] || $post['owner_user_id'] == $user['id']);
$isPublic = ($post['visibility'] ?? 'private') === 'public';
// Check if accessed via valid share token containing this item
$hasAccess = $isOwner || $isPublic;

if (!$hasAccess) {
    // If user is not logged in, redirect to login
    if (!$user) {
        header('Location: /login.php');
        exit;
    }

    // If user is logged in but not admin, deny access
    // Admins are allowed to see everything
    if (($user['role'] ?? '') !== 'admin') {
         http_response_code(403);
         die("Dostop zavrnjen. Ta vsebina je zasebna.");
    }
}

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

$title = $post['id'] . ' ' . ($post['type'] === 'video' ? 'video' : 'slika');
render_header($title, $user);

$previewSrc = !empty($post['preview_path']) ? '/' . $post['preview_path'] : '/' . $post['file_path'];
$originalSrc = '/' . $post['file_path'];
$thumbSrc = !empty($post['thumb_path']) ? '/' . $post['thumb_path'] : $previewSrc;

$mediaHtml = '';
if ($post['type'] === 'video') {
    $poster = htmlspecialchars($previewSrc);
    $src = htmlspecialchars($originalSrc);
    $mediaHtml = "<video src=\"{$src}\" poster=\"{$poster}\" controls autoplay muted loop style=\"max-width:100%;max-height:100%;\"></video>";
} else {
    $p = htmlspecialchars($previewSrc);
    $o = htmlspecialchars($originalSrc);
    // Click to load full res if different, or just zoom
    $mediaHtml = "<img src=\"{$p}\" data-original=\"{$o}\" alt=\"{$title}\" class=\"preview-image\" loading=\"lazy\" onclick=\"this.src=this.dataset.original; this.classList.remove('preview-image');\" style=\"max-width:100%;max-height:100%;object-fit:contain;image-orientation:from-image;\">";
}

$shareUrl = '/view.php?s=' . urlencode($post['share_token'] ?? '');
?>
<div class="view-page">
    <div class="media-panel">
        <?php echo $mediaHtml; ?>
    </div>
    <div class="info-panel">
        <h1 style="font-size:1.5rem; margin-bottom:0.5rem;"><?php echo htmlspecialchars($title); ?></h1>
        <p class="meta" style="color:var(--muted); margin-bottom:1rem;">Objavil <?php echo htmlspecialchars($post['username'] ?? 'Anon'); ?></p>

        <?php if ($post['description']) : ?>
            <p style="margin-bottom:1.5rem; line-height:1.6;"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
        <?php endif; ?>

        <div class="stats-bar" style="display:flex; gap:1rem; margin-bottom:1.5rem; font-size:0.9rem; color:var(--muted);">
            <span id="viewCount" style="display:flex;align-items:center;gap:0.3rem;"><span class="material-icons" style="font-size:1rem;">visibility</span> <?php echo (int)$post['views']; ?></span>
            <span style="display:flex;align-items:center;gap:0.3rem;"><span class="material-icons" style="font-size:1rem;">event</span> <?php echo date('d.m.Y', (int)$post['created_at']); ?></span>
        </div>

        <div class="actions" style="display:flex; gap:0.5rem; margin-bottom:2rem; flex-wrap:wrap;">
            <button class="button like <?php echo $liked ? 'active' : ''; ?>" id="likeBtn" data-id="<?php echo $post['id']; ?>" style="flex:1;">
                <span class="like-icon" style="margin-right:0.5rem;"><?php echo $liked ? '❤️' : '🤍'; ?></span>
                <span class="like-label"><?php echo $liked ? 'Všečkano' : 'Všečkaj'; ?></span>
                <span class="like-count" style="margin-left:auto; background:rgba(255,255,255,0.1); padding:0.1rem 0.5rem; border-radius:1rem;"><?php echo $likeCount; ?></span>
            </button>
            <button class="button ghost js-share-btn" data-url="<?php echo $shareUrl; ?>">Deli 🔗</button>
            <?php if ($user && ($user['role'] === 'admin' || $user['id'] === $post['user_id'])): ?>
                <button class="button danger js-delete-btn" data-id="<?php echo $post['id']; ?>">Izbriši 🗑️</button>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <div style="flex:1; display:flex; gap:0.5rem;">
                     <!-- Visibility Toggle -->
                    <select id="visibilitySelect" data-id="<?php echo $post['id']; ?>" class="button ghost" style="flex:1; appearance:none; padding-right:1rem; text-align:center;">
                        <option value="private" <?php echo ($post['visibility'] === 'private') ? 'selected' : ''; ?>>🔒 Zasebno</option>
                        <option value="public" <?php echo ($post['visibility'] === 'public') ? 'selected' : ''; ?>>🌍 Javno</option>
                    </select>

                    <button class="button ghost js-share-btn" data-url="<?php echo $shareUrl; ?>" style="flex:1;">
                        Deli <span class="material-icons" style="font-size:1rem;margin-left:0.5rem;">share</span>
                    </button>
                </div>
                <button class="button danger icon-only js-delete-btn" data-id="<?php echo $post['id']; ?>" title="Izbriši"><span class="material-icons">delete</span></button>
            <?php endif; ?>
        </div>

        <section class="comments" id="commentsSection" data-id="<?php echo $post['id']; ?>">
            <h2 style="font-size:1.2rem; margin-bottom:1rem;">Komentarji</h2>
            <div class="comment-list" id="commentList" style="margin-bottom:1.5rem; max-height:300px; overflow-y:auto;">
                <!-- Loaded via JS -->
            </div>
            <?php if ($user) : ?>
                <form class="comment-form" id="commentForm" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <textarea name="body" rows="2" placeholder="Dodaj komentar..." required style="width:100%; padding:0.5rem; border-radius:0.5rem; border:1px solid var(--border); background:rgba(255,255,255,0.05); color:white;"></textarea>
                    <button class="button small" type="submit">Objavi</button>
                </form>
            <?php else : ?>
                <p class="muted">Prijavi se za komentiranje.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
render_footer();
