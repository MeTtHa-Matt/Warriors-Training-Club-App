<?php
require_once 'includes/general/administration.php';

$commitsPath = __DIR__ . '/data/commits.json';
$commits = [];
if (is_file($commitsPath)) {
    $json = @file_get_contents($commitsPath);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $commits = array_reverse($decoded);
        }
    }
}

$latest = array_slice($commits, 0, 40);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commits GitHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
</head>

<body>
    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <span class="hero-badge mb-3"><span class="dot"></span>Administration</span>
            <h1 class="mt-3 mb-2">Commits reçus via GitHub</h1>
            <p class="lead mb-0">Les derniers commits reçus par le webhook GitHub pour le dépôt.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="row g-4">
                <div class="col-12">
                    <div class="info-card p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <div class="info-card__title">Historique commits</div>
                                <div class="info-card__value display-6"><?= number_format(count($commits), 0, ',', ' ') ?></div>
                            </div>
                            <a href="data/commits.json" class="btn btn-wtc-gold rounded-pill px-4" target="_blank" rel="noopener">
                                <i class="bi bi-download me-2"></i>Voir le JSON brut
                            </a>
                        </div>
                        <p class="mb-0 text-white">Les commits sont stockés dans <strong>data/commits.json</strong> via le webhook public <strong>/github-webhook.php</strong>.</p>
                    </div>
                </div>

                <div class="col-12">
                    <div class="info-card p-4">
                        <div class="info-card__title mb-3">Derniers commits</div>
                        <?php if (empty($latest)): ?>
                            <p class="mb-0 text-white">Aucun commit enregistré pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Horodatage</th>
                                            <th>SHA</th>
                                            <th>Message</th>
                                            <th>Auteur</th>
                                            <th>Dépôt</th>
                                            <th>Lien</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latest as $c): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($c['timestamp'] ?? '')) ?></td>
                                                <td><code><?= htmlspecialchars(substr((string) ($c['id'] ?? ''), 0, 12)) ?></code></td>
                                                <td><?= htmlspecialchars((string) ($c['message'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($c['author'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($c['repo'] ?? '')) ?></td>
                                                <td>
                                                    <?php if (!empty($c['url'])): ?>
                                                        <a href="<?= htmlspecialchars($c['url']) ?>" target="_blank" rel="noopener">Voir</a>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
