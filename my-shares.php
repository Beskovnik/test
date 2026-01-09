<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::requireLogin();
$pdo = Database::connect();

render_header('Moje Delitve', $user, 'myshares');

// Fetch Shares (Collections)
$stmt = $pdo->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM share_items WHERE share_id = s.id) as item_count
    FROM shares s
    WHERE s.owner_user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user['id']]);
$shares = $stmt->fetchAll();

// Fetch Public Links (Single Items)
$stmtPublic = $pdo->prepare("
    SELECT * FROM posts
    WHERE owner_user_id = ? AND is_public = 1
    ORDER BY created_at DESC
");
$stmtPublic->execute([$user['id']]);
$publicLinks = $stmtPublic->fetchAll();

?>
<div class="container" style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
    <h1 style="margin-bottom: 2rem;">Moje Delitve</h1>

    <!-- 1. Collection Shares -->
    <h2 style="font-size:1.2rem; margin-bottom:1rem; color:var(--muted); border-bottom:1px solid var(--border); padding-bottom:0.5rem;">Zbirke (Albumi)</h2>
    <?php if (empty($shares)): ?>
        <p class="muted" style="margin-bottom:2rem;">Nimate aktivnih deljenih zbirk.</p>
    <?php else: ?>
        <div class="table-responsive" style="margin-bottom:3rem;">
            <table class="table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom: 1px solid var(--border);">
                        <th style="padding:1rem;">Naslov</th>
                        <th style="padding:1rem;">Ustvarjeno</th>
                        <th style="padding:1rem;">Elementov</th>
                        <th style="padding:1rem;">Ogledov</th>
                        <th style="padding:1rem;">Prenosov</th>
                        <th style="padding:1rem;">Status</th>
                        <th style="padding:1rem;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shares as $share): ?>
                        <tr style="border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.02);">
                            <td style="padding:1rem; font-weight:bold;">
                                <a href="/share.php?token=<?php echo $share['token']; ?>" target="_blank" style="color:var(--text); text-decoration:none;">
                                    <?php echo htmlspecialchars($share['title'] ?: 'Untitled'); ?> <span class="material-icons" style="font-size:0.8rem;vertical-align:middle;">open_in_new</span>
                                </a>
                            </td>
                            <td style="padding:1rem; color:var(--muted);"><?php echo date('d.m.Y', (int)$share['created_at']); ?></td>
                            <td style="padding:1rem;"><?php echo $share['item_count']; ?></td>
                            <td style="padding:1rem;"><?php echo $share['views_total']; ?></td>
                            <td style="padding:1rem;"><?php echo $share['downloads_total']; ?></td>
                            <td style="padding:1rem;">
                                <?php if ($share['is_active']): ?>
                                    <span class="badge success">Aktivno</span>
                                <?php else: ?>
                                    <span class="badge danger">Onemogočeno</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:1rem;">
                                <button class="button small ghost" onclick="copyLink('<?php echo $share['token']; ?>', 'share')">Link</button>
                                <a href="/my-shares-analytics.php?id=<?php echo $share['id']; ?>" class="button small ghost">Analitika</a>
                                <?php if ($share['is_active']): ?>
                                    <button class="button small danger" onclick="toggleShare(<?php echo $share['id']; ?>, 'disable')">Stop</button>
                                <?php endif; ?>
                                <button class="button small danger icon-only" onclick="toggleShare(<?php echo $share['id']; ?>, 'delete')"><span class="material-icons">delete</span></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 2. Public Links (Single Items) -->
    <h2 style="font-size:1.2rem; margin-bottom:1rem; color:var(--muted); border-bottom:1px solid var(--border); padding-bottom:0.5rem;">Javne Povezave (Posamezni mediji)</h2>
    <?php if (empty($publicLinks)): ?>
        <p class="muted">Nimate aktivnih javnih povezav.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom: 1px solid var(--border);">
                        <th style="padding:1rem;">Predogled</th>
                        <th style="padding:1rem;">Medij</th>
                        <th style="padding:1rem;">Ustvarjeno</th>
                        <th style="padding:1rem;">Ogledov</th>
                        <th style="padding:1rem;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publicLinks as $link): ?>
                        <tr style="border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.02);">
                            <td style="padding:1rem; width:100px;">
                                <?php $thumb = $link['thumb_path'] ?: $link['preview_path']; ?>
                                <img src="/<?php echo htmlspecialchars($thumb); ?>" style="width:80px; height:60px; object-fit:cover; border-radius:0.5rem;">
                            </td>
                            <td style="padding:1rem; font-weight:bold;">
                                <a href="/public.php?t=<?php echo $link['public_token']; ?>" target="_blank" style="color:var(--text); text-decoration:none;">
                                    <?php echo htmlspecialchars($link['title'] ?: $link['id'] . ' ' . $link['type']); ?>
                                    <span class="material-icons" style="font-size:0.8rem;vertical-align:middle;">open_in_new</span>
                                </a>
                            </td>
                            <td style="padding:1rem; color:var(--muted);"><?php echo date('d.m.Y', (int)$link['created_at']); ?></td>
                            <td style="padding:1rem;"><?php echo (int)$link['views']; ?></td>
                            <td style="padding:1rem;">
                                <button class="button small ghost" onclick="copyLink('<?php echo $link['public_token']; ?>', 'public')">Link</button>
                                <button class="button small danger" onclick="revokePublicLink(<?php echo $link['id']; ?>)">Prekliči</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function copyLink(token, type) {
    let url = '';
    if (type === 'public') {
        url = window.location.origin + '/public.php?t=' + token;
    } else {
        url = window.location.origin + '/share.php?token=' + token;
    }
    navigator.clipboard.writeText(url).then(() => {
        // Simple toast or alert
        if (window.showToast) window.showToast('Povezava kopirana!');
        else alert('Link kopiran!');
    });
}

async function revokePublicLink(id) {
    if (!confirm('Ali želite preklicati javno povezavo? Medij bo postal zaseben.')) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const res = await fetch('/api/media/visibility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({media_id: id, visibility: 'private'})
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Napaka');
        }
    } catch (e) {
        alert('Napaka omrežja');
    }
}

async function toggleShare(id, action) {
    if (!confirm('Ste prepričani?')) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const res = await fetch('/api/share/manage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({share_id: id, action: action})
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error);
        }
    } catch (e) {
        alert('Napaka');
    }
}
</script>

<?php render_footer(); ?>
