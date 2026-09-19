<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (is_file(__DIR__ . '/.env')) {
    Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();
}

$whatsappLink = getenv('LIEN_WHATSAPP');
if ($whatsappLink === false || $whatsappLink === '') {
    $whatsappLink = $_ENV['LIEN_WHATSAPP'] ?? $_SERVER['LIEN_WHATSAPP'] ?? '';
}

$whatsappLink = trim((string) $whatsappLink);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warriors Training Club - Groupe WhatsApp</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" href="./img/wtc.png">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Communauté du club</span>
                    <h1 class="mt-3 mb-3">Rejoindre le groupe <span class="accent">WhatsApp</span></h1>
                    <p class="lead">Cliquez ici pour rejoindre le groupe WhatsApp du Warriors Training Club.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="groupe-whatsapp">
        <div class="container">
            <div class="auth-wrapper text-center">
                <i class="bi bi-whatsapp display-3 text-success" aria-hidden="true"></i>
                <h2 class="mt-3">Le groupe du club</h2>
                <p class="lead mb-4">Rejoignez les membres du club pour suivre les informations et les actualités.</p>
                <?php if ($whatsappLink !== ''): ?>
                    <a href="<?= htmlspecialchars($whatsappLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"
                        class="btn btn-wtc-gold rounded-pill px-4">
                        <i class="bi bi-whatsapp me-2"></i>Rejoindre le groupe WhatsApp
                    </a>
                <?php else: ?>
                    <p class="auth-alert auth-alert--error mb-0">Le lien du groupe WhatsApp n'est pas encore disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>

</html>