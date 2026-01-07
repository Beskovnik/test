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
$isPublic = ($post['visibility'] ?? 'private') === 'public' || ($post['is_public'] ?? 0) == 1;
// Check if accessed via valid share token containing this item
$hasAccess = $isOwner || $isPublic;

// Prepare Public URL if it exists
$publicLink = '';
if (!empty($post['public_token']) && !empty($post['is_public'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $publicLink = $protocol . $host . '/public.php?t=' . $post['public_token'];
}

if (!$hasAccess && !$user) {
    // If user is not logged in and no access, redirect to login
    header('Location: /login.php');
    exit;
}

if (!$hasAccess && ($user['role'] ?? '') !== 'admin') {
    // If user is logged in but not owner and not admin
    http_response_code(403);
    die("Dostop zavrnjen. Ta vsebina je zasebna.");
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

// Use Optimized Path if available, then Preview (legacy), then Original
$previewSrc = !empty($post['optimized_path']) ? '/' . $post['optimized_path'] :
              (!empty($post['preview_path']) ? '/' . $post['preview_path'] :
              '/' . $post['file_path']);

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

            <?php if ($isOwner): ?>
                <div style="width:100%; display:flex; flex-direction:column; gap:1rem; margin-top:0.5rem; background:rgba(255,255,255,0.03); padding:1rem; border-radius:1rem; border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:1rem;">Dostopnost</h3>
                        <span id="statusBadge" class="badge <?php echo $isPublic ? 'success' : ''; ?>" style="font-size:0.8rem; padding:0.3rem 0.6rem;">
                            <?php echo $isPublic ? 'Javno' : 'Zasebno'; ?>
                        </span>
                    </div>

                    <!-- Visibility Toggle (Legacy/Sync) -->
                    <select id="visibilitySelect" data-id="<?php echo $post['id']; ?>" class="button ghost small" style="width:100%; text-align:left;">
                        <option value="private" <?php echo ($post['visibility'] === 'private') ? 'selected' : ''; ?>>🔒 Zasebno (Samo jaz)</option>
                        <option value="public" <?php echo ($post['visibility'] === 'public') ? 'selected' : ''; ?>>🌍 Javno (Galerija)</option>
                    </select>

                    <button id="publicLinkBtn" class="button primary" style="width:100%;">
                        Generiraj javni URL link 🔗
                    </button>

                    <div id="publicLinkContainer" style="display:<?php echo empty($publicLink) ? 'none' : 'block'; ?>; margin-top:0.5rem;">
                        <label style="font-size:0.8rem; color:var(--muted); display:block; margin-bottom:0.25rem;">Javni URL:</label>
                        <div style="display:flex; gap:0.5rem;">
                            <input type="text" readonly value="<?php echo htmlspecialchars($publicLink); ?>" style="flex:1; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:var(--text); padding:0.5rem; border-radius:0.5rem; font-size:0.9rem;">
                            <button id="copyPublicLinkBtn" class="button icon-only" title="Kopiraj"><span class="material-icons">content_copy</span></button>
                        </div>
                    </div>

                    <button class="button danger small icon-only js-delete-btn" data-id="<?php echo $post['id']; ?>" title="Izbriši" style="align-self: flex-end;"><span class="material-icons">delete</span></button>
                </div>
            <?php else: ?>
                 <button class="button ghost js-share-btn" data-url="<?php echo $shareUrl; ?>">Deli 🔗</button>
                 <?php if ($user && $user['role'] === 'admin'): ?>
                    <button class="button danger js-delete-btn" data-id="<?php echo $post['id']; ?>">Izbriši 🗑️</button>
                 <?php endif; ?>
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
