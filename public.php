<?php
require_once __DIR__ . '/app/Bootstrap.php';

use App\Database;

$token = $_GET['t'] ?? null;

if (!$token) {
    http_response_code(404);
    echo "Ni najdeno.";
    exit;
}

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM posts WHERE public_token = :token AND is_public = 1');
$stmt->execute([':token' => $token]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo '<div style="color:white;text-align:center;padding:5rem;font-family:sans-serif;background:#1a1c25;height:100vh;">Vsebina ni na voljo ali pa je povezava potekla.</div>';
    exit;
}

// Render simple view
// Since we are in the root, we can include layout if we want, but user requested "prikazati sliko (brez admin gumbov)".
// So maybe a stripped down layout. But to keep styling consistent, I might reuse layout but hide elements?
// Or just a raw view.
// Let's use layout but minimal content.

require_once __DIR__ . '/includes/layout.php';

$title = $post['id'] . ' ' . ($post['type'] === 'video' ? 'video' : 'slika');

// Mock user as null so header doesn't show login info (though `render_header` handles it).
render_header($title, null); // null user

$previewSrc = !empty($post['preview_path']) ? '/' . $post['preview_path'] : '/' . $post['file_path'];
$originalSrc = '/' . $post['file_path'];

$mediaHtml = '';
if ($post['type'] === 'video') {
    $poster = htmlspecialchars($previewSrc);
    $src = htmlspecialchars($originalSrc);
    $mediaHtml = "<video src=\"{$src}\" poster=\"{$poster}\" controls autoplay muted loop style=\"max-width:100%;max-height:100%;\"></video>";
} else {
    $p = htmlspecialchars($previewSrc);
    $o = htmlspecialchars($originalSrc);
    $mediaHtml = "<img src=\"{$p}\" data-original=\"{$o}\" alt=\"{$title}\" class=\"preview-image\" loading=\"lazy\" onclick=\"this.src=this.dataset.original; this.classList.remove('preview-image');\" style=\"max-width:100%;max-height:100%;object-fit:contain;image-orientation:from-image;\">";
}
?>

<div class="view-page" style="height: calc(100vh - 60px); display: flex; align-items: center; justify-content: center; overflow: hidden;">
    <div class="media-panel" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
        <?php echo $mediaHtml; ?>
    </div>
</div>

<?php
// Simple footer
echo "</body></html>";
?>
