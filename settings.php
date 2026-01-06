<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php';

use App\Auth;

$user = Auth::user();

render_header('Pravila', $user, 'settings');
render_flash($_SESSION['flash'] ?? null);
unset($_SESSION['flash']);

$config = app_config();
$maxSizeGB = round($config['max_image_size'] / (1024 * 1024 * 1024), 2);
?>
<div class="settings-page">
    <div class="card form" style="max-width: 800px; width: 100%;">
        <h1>Pravila in Informacije</h1>

        <div style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; color: var(--accent); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Splošna pravila</h2>
            <p style="line-height: 1.6;">Dobrodošli v naši galeriji. Prosimo, da se držite naslednjih pravil:</p>
            <ul style="list-style-type: disc; padding-left: 1.5rem; color: var(--text);">
                <li style="margin-bottom: 0.5rem;">Objavljajte le vsebine, za katere imate avtorske pravice.</li>
                <li style="margin-bottom: 0.5rem;">Prepovedana je vsebina, ki spodbuja sovraštvo, nasilje ali je nezakonita.</li>
                <li style="margin-bottom: 0.5rem;">Vse objave so javno vidne.</li>
                <li style="margin-bottom: 0.5rem;">Spoštujte zasebnost drugih uporabnikov.</li>
            </ul>
        </div>

        <div style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; color: var(--accent); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Tehnične omejitve</h2>
            <ul style="list-style-type: disc; padding-left: 1.5rem; color: var(--text);">
                <li style="margin-bottom: 0.5rem;"><strong>Maksimalna velikost datoteke:</strong> <?php echo $maxSizeGB; ?> GB</li>
                <li style="margin-bottom: 0.5rem;"><strong>Podprti slikovni formati:</strong> <?php echo implode(', ', array_map(fn($m) => str_replace('image/', '', $m), $config['allowed_images'])); ?></li>
                <li style="margin-bottom: 0.5rem;"><strong>Podprti video formati:</strong> <?php echo implode(', ', array_map(fn($m) => str_replace('video/', '', $m), $config['allowed_videos'])); ?></li>
            </ul>
        </div>

        <div>
            <h2 style="font-size: 1.5rem; color: var(--accent); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">Politika zasebnosti</h2>
            <p style="line-height: 1.6;">Vaši osebni podatki (email) se uporabljajo izključno za prijavo in obveščanje. Gesla so šifrirana. Objavljene slike so javne in dostopne vsem obiskovalcem spletne strani.</p>
        </div>
    </div>
</div>
<?php
render_footer();
