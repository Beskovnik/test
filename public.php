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
// Use the new columns
$stmt = $pdo->prepare('SELECT * FROM posts WHERE public_token = :token AND is_public = 1');
$stmt->execute([':token' => $token]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('<div style="font-family:sans-serif; text-align:center; padding:5rem; color:#888;">Vsebina ni na voljo ali pa je povezava potekla.</div>');
}

// Data Preparation
$title = $post['title'] ? $post['title'] : ($post['id'] . ' ' . ($post['type'] === 'video' ? 'video' : 'slika'));
$previewSrc = !empty($post['preview_path']) ? '/' . $post['preview_path'] : '/' . $post['file_path'];
$originalSrc = '/' . $post['file_path'];
$downloadUrl = '/api/download.php?token=' . $token;
$dateStr = date('d.m.Y', (int)$post['created_at']);
$mime = $post['mime'];

// OG Meta
$ogImage = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $previewSrc;

?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Javni ogled - <?php echo htmlspecialchars($title); ?></title>

    <!-- Meta -->
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta name="robots" content="noindex, nofollow">

    <style>
        :root {
            --bg: #0f0c29;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text: #e6e7ea;
            --muted: #94a3b8;
            --accent: #6d5dfc;
            --glass-border: rgba(255, 255, 255, 0.1);
            --blur: 20px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            background-image:
                radial-gradient(circle at 20% 20%, rgba(109, 93, 252, 0.15), transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(255, 71, 87, 0.1), transparent 40%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .viewer-container {
            width: 100%;
            max-width: 1200px;
            height: 90vh; /* Fill most of screen */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            box-sizing: border-box;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            padding: 1.5rem;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }

        .header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1rem;
            flex-shrink: 0;
        }

        .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .meta-info {
            text-align: center;
        }

        .title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: #fff;
        }

        .date {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }

        .media-wrapper {
            flex: 1;
            width: 100%;
            min-height: 0; /* Important for flex child scrolling/sizing */
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.2);
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
        }

        .media-content {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }

        video.media-content {
            cursor: default;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        .btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: var(--text);
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            border-color: rgba(255,255,255,0.3);
        }

        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .btn.primary:hover {
            filter: brightness(1.1);
        }

        /* Lightbox Overlay */
        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .lightbox.active {
            opacity: 1;
            pointer-events: auto;
        }

        .lightbox img {
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.2, 0, 0.2, 1);
        }

        .lightbox.active img {
            transform: scale(1);
        }

        .close-btn {
            position: absolute;
            top: 2rem;
            right: 2rem;
            color: #fff;
            background: transparent;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            opacity: 0.7;
        }
        .close-btn:hover { opacity: 1; }

        @media (max-width: 768px) {
            .viewer-container {
                height: 100vh;
                padding: 0;
            }
            .glass-card {
                border-radius: 0;
                border: none;
            }
        }
    </style>
</head>
<body>

<div class="viewer-container">
    <div class="glass-card">
        <div class="header">
            <span class="label">Javni ogled</span>
            <div class="meta-info">
                <h1 class="title"><?php echo htmlspecialchars($title); ?></h1>
                <div class="date"><?php echo $dateStr; ?></div>
            </div>
        </div>

        <div class="media-wrapper">
            <?php if ($post['type'] === 'video'): ?>
                <video class="media-content" controls autoplay muted loop playsinline poster="<?php echo htmlspecialchars($previewSrc); ?>">
                    <source src="<?php echo htmlspecialchars($originalSrc); ?>" type="<?php echo htmlspecialchars($mime); ?>">
                    Vaš brskalnik ne podpira videa.
                </video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($previewSrc); ?>"
                     data-full="<?php echo htmlspecialchars($originalSrc); ?>"
                     alt="<?php echo htmlspecialchars($title); ?>"
                     class="media-content"
                     loading="lazy"
                     id="mainImage">
            <?php endif; ?>
        </div>

        <div class="actions">
            <button class="btn" id="copyBtn">
                <span>🔗</span> Kopiraj povezavo
            </button>
            <a href="<?php echo $downloadUrl; ?>" class="btn primary" download>
                <span>⬇️</span> Prenesi
            </a>
        </div>
    </div>
</div>

<div class="lightbox" id="lightbox">
    <button class="close-btn">&times;</button>
    <img src="" id="lightboxImg">
</div>

<script>
    // Copy Link
    document.getElementById('copyBtn').addEventListener('click', async function() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            const originalText = this.innerHTML;
            this.innerHTML = '<span>✅</span> Kopirano!';
            setTimeout(() => this.innerHTML = originalText, 2000);
        } catch (err) {
            alert('Povezava: ' + window.location.href);
        }
    });

    // Lightbox Logic (Only for images)
    const mainImage = document.getElementById('mainImage');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const closeBtn = document.querySelector('.close-btn');

    if (mainImage) {
        mainImage.addEventListener('click', () => {
            const fullSrc = mainImage.dataset.full || mainImage.src;
            lightboxImg.src = fullSrc;
            lightbox.classList.add('active');
        });

        // Close handlers
        const close = () => lightbox.classList.remove('active');
        closeBtn.addEventListener('click', close);
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    }
</script>

</body>
</html>
