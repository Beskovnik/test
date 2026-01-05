<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/layout.php';

$user = current_user($pdo);

render_header('Nastavitve', $user, 'settings');
render_flash($flash ?? null);

$config = app_config();
$maxSizeGB = $config['max_image_size'] / (1024 * 1024 * 1024);
?>
<div class="settings-page">
    <div class="card form" style="max-width: 800px; width: 100%;">
        <h1>Pravila in Informacije</h1>

        <section class="info-section">
            <h2>Splošna pravila</h2>
            <p>Dobrodošli v naši galeriji. Prosimo, da se držite naslednjih pravil:</p>
            <ul>
                <li>Objavljajte le vsebine, za katere imate avtorske pravice.</li>
                <li>Prepovedana je vsebina, ki spodbuja sovraštvo, nasilje ali je nezakonita.</li>
                <li>Vse objave so javno vidne.</li>
                <li>Spoštujte zasebnost drugih uporabnikov.</li>
            </ul>
        </section>

        <section class="info-section">
            <h2>Tehnične omejitve</h2>
            <ul>
                <li><strong>Maksimalna velikost datoteke:</strong> <?php echo $maxSizeGB; ?> GB</li>
                <li><strong>Podprti slikovni formati:</strong> <?php echo implode(', ', array_map(fn($m) => str_replace('image/', '', $m), $config['allowed_images'])); ?></li>
                <li><strong>Podprti video formati:</strong> <?php echo implode(', ', array_map(fn($m) => str_replace('video/', '', $m), $config['allowed_videos'])); ?></li>
            </ul>
        </section>

        <section class="info-section">
            <h2>Politika zasebnosti</h2>
            <p>Vaši osebni podatki (email) se uporabljajo izključno za prijavo in obveščanje. Gesla so šifrirana. Objavljene slike so javne in dostopne vsem obiskovalcem spletne strani.</p>
        </section>
    </div>
</div>

<style>
    .info-section {
        margin-bottom: 2rem;
    }
    .info-section h2 {
        font-size: 1.5rem;
        color: var(--accent);
        margin-bottom: 1rem;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }
    .info-section ul {
        list-style-type: disc;
        padding-left: 1.5rem;
        color: var(--text);
    }
    .info-section li {
        margin-bottom: 0.5rem;
    }
    .info-section p {
        line-height: 1.6;
    }
</style>
<?php
render_footer();
