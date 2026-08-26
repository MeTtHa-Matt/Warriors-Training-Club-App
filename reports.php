<?php
require_once 'includes/general/reports-admin.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202608260900">
    <link rel="icon" type="image/png" href="img/wtc.png">
</head>
<body>
<?php require 'includes/general/navbar.php'; ?>
<section class="hero hero--compact">
    <div class="container">
        <span class="hero-badge mb-3"><span class="dot"></span>Administration</span>
        <h1 class="mt-3 mb-3">Boîte des <span class="accent">signalements</span></h1>
        <p class="lead">Les messages vérifiés par e-mail, regroupés comme des conversations.</p>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if (empty($reports)): ?>
            <div class="admin-empty-state"><i class="bi bi-inbox"></i><p class="mb-0">Aucun signalement pour le moment.</p></div>
        <?php else: ?>
            <div class="report-inbox">
                <?php foreach ($reports as $report): ?>
                    <article class="report-thread">
                        <div class="report-thread__head">
                            <div><span class="report-thread__label">Conversation</span><h2><?= htmlspecialchars($report['email'] ?? 'Adresse inconnue') ?></h2></div>
                            <time datetime="<?= htmlspecialchars($report['created_at'] ?? '') ?>"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($report['created_at'] ?? 'now'))) ?></time>
                        </div>
                        <div class="report-thread__bubble"><?= nl2br(htmlspecialchars($report['message'] ?? '')) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require 'includes/general/footer.php'; ?>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
