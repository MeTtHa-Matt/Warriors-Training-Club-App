<?php
require_once 'includes/general/administration.php';

$dbAuditPath = __DIR__ . '/data/db_audit.json';
$rawLogs = [];
if (is_file($dbAuditPath)) {
    $json = @file_get_contents($dbAuditPath);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $rawLogs = $decoded;
        }
    }
}

$logs = array_values($rawLogs);
$latestLogs = array_slice($logs, -40);
$reverseLogs = array_reverse($latestLogs);

$deletedEntries = array_filter($logs, static function (array $entry): bool {
    return stripos((string) ($entry['sql'] ?? ''), 'delete from') !== false;
});
$deletedEntries = array_slice(array_reverse($deletedEntries), 0, 20);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit base de données</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
</head>

<body>
    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <span class="hero-badge mb-3"><span class="dot"></span>Audit</span>
            <h1 class="mt-3 mb-2">Journal des actions base de données</h1>
            <p class="lead mb-0">Suivi des requêtes SQL exécutées par l’application, avec les suppressions et autres opérations critiques.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="row g-4">
                <div class="col-12">
                    <div class="info-card p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <div class="info-card__title">Historique JSON</div>
                                <div class="info-card__value display-6"><?= number_format(count($logs), 0, ',', ' ') ?></div>
                            </div>
                            <a href="data/db_audit.json" class="btn btn-wtc-gold rounded-pill px-4" target="_blank" rel="noopener">
                                <i class="bi bi-download me-2"></i>Voir le JSON brut
                            </a>
                        </div>
                        <p class="mb-0 text-white">Le fichier est stocké dans <strong>data/db_audit.json</strong> et se met à jour à chaque requête SQL exécutée.</p>
                    </div>
                </div>

                <div class="col-12">
                    <div class="info-card p-4">
                        <div class="info-card__title mb-3">Dernières 40 actions</div>
                        <?php if (empty($reverseLogs)): ?>
                            <p class="mb-0 text-white">Aucune action enregistrée pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Horodatage</th>
                                            <th>Événement</th>
                                            <th>SQL</th>
                                            <th>Statut</th>
                                            <th>Contexte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reverseLogs as $entry): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($entry['timestamp'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($entry['event'] ?? '')) ?></td>
                                                <td><code><?= htmlspecialchars((string) ($entry['sql'] ?? '')) ?></code></td>
                                                <td><?= htmlspecialchars((string) ($entry['status'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($entry['context'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12">
                    <div class="info-card p-4">
                        <div class="info-card__title mb-3">20 dernières suppressions détectées</div>
                        <?php if (empty($deletedEntries)): ?>
                            <p class="mb-0 text-white">Aucune suppression enregistrée.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Horodatage</th>
                                            <th>Requête</th>
                                            <th>Contexte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deletedEntries as $entry): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($entry['timestamp'] ?? '')) ?></td>
                                                <td><code><?= htmlspecialchars((string) ($entry['sql'] ?? '')) ?></code></td>
                                                <td><?= htmlspecialchars((string) ($entry['context'] ?? '')) ?></td>
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
