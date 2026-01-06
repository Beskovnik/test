<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::requireLogin();
$pdo = Database::connect();

render_header('Moje Delitve', $user, 'myshares');

// Fetch Shares
$stmt = $pdo->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM share_items WHERE share_id = s.id) as item_count
    FROM shares s
    WHERE s.owner_user_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$user['id']]);
$shares = $stmt->fetchAll();

?>
<div class="container" style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
    <h1 style="margin-bottom: 2rem;">Moje Delitve</h1>

    <?php if (empty($shares)): ?>
        <p class="muted">Nimate še aktivnih delitev.</p>
    <?php else: ?>
        <div class="table-responsive">
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
                                <button class="button small ghost" onclick="copyLink('<?php echo $share['token']; ?>')">Link</button>
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
</div>

<script>
function copyLink(token) {
    const url = window.location.origin + '/share.php?token=' + token;
    navigator.clipboard.writeText(url).then(() => alert('Link kopiran!'));
}

async function toggleShare(id, action) {
    if (!confirm('Ste prepričani?')) return;

    try {
        const res = await fetch('/api/share/manage.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
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
