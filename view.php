<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/layout.php';

$user = current_user($pdo);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$share = $_GET['s'] ?? null;

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
    echo 'Not found';
    exit;
}

if ($post['visibility'] !== 'public' && !$user) {
    redirect('/login.php');
}

$likeCount = fetch_like_count($pdo, (int)$post['id']);
$liked = false;
if ($user) {
    $stmt = $pdo->prepare('SELECT 1 FROM likes WHERE post_id = :post AND user_id = :user');
    $stmt->execute([':post' => $post['id'], ':user' => $user['id']]);
    $liked = (bool)$stmt->fetchColumn();
}

render_header($post['title'] ?: 'Ogled', $user);
render_flash($flash ?? null);

$media = $post['type'] === 'video'
    ? '<video src="/' . htmlspecialchars($post['file_path'], ENT_QUOTES, 'UTF-8') . '" controls></video>'
    : '<img src="/' . htmlspecialchars($post['file_path'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($post['title'] ?? '', ENT_QUOTES, 'UTF-8') . '">';

$shareUrl = '/view.php?s=' . urlencode($post['share_token']);

?>
<div class="view-page">
    <div class="media-panel">
        <?php echo $media; ?>
    </div>
    <div class="info-panel">
        <h1><?php echo htmlspecialchars($post['title'] ?: 'Brez naslova', ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="meta">Objavil <?php echo htmlspecialchars($post['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($post['description']) : ?>
            <p><?php echo nl2br(htmlspecialchars($post['description'], ENT_QUOTES, 'UTF-8')); ?></p>
        <?php endif; ?>
        <div class="actions">
            <button class="button like" data-post-id="<?php echo (int)$post['id']; ?>">
                <span class="like-label"><?php echo $liked ? 'Všečkano' : 'Všečkaj'; ?></span>
                <span class="like-count"><?php echo $likeCount; ?></span>
            </button>
            <button class="button ghost" data-copy="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>">Deli</button>
        </div>
        <section class="comments" data-post-id="<?php echo (int)$post['id']; ?>">
            <h2>Komentarji</h2>
            <div class="comment-list"></div>
            <?php if ($user) : ?>
                <form class="comment-form">
                    <?php echo csrf_field(); ?>
                    <textarea name="body" rows="3" placeholder="Dodaj komentar" required></textarea>
                    <button class="button" type="submit">Objavi</button>
                </form>
            <?php else : ?>
                <p class="muted">Za komentiranje se prijavite.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
render_footer();
