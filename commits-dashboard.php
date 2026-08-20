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

// If no commits stored yet, try to fetch from GitHub API for the configured repo
if (empty($commits)) {
    $repo = 'MeTtHa-Matt/Warriors-Training-Club-App';
    $apiUrl = "https://api.github.com/repos/" . $repo . "/commits?per_page=100";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github.v3+json\r\n",
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents($apiUrl, false, $context);
    if ($raw !== false) {
        $items = json_decode($raw, true);
        if (is_array($items)) {
            $fetched = [];
            foreach ($items as $it) {
                $id = $it['sha'] ?? '';
                $fetched[] = [
                    'id' => $id,
                    'message' => $it['commit']['message'] ?? '',
                    'url' => $it['html_url'] ?? '',
                    'author' => $it['commit']['author']['name'] ?? ($it['author']['login'] ?? ''),
                    'timestamp' => $it['commit']['author']['date'] ?? '',
                    'repo' => $repo,
                ];
            }
            if (!empty($fetched)) {
                // store and use fetched commits
                @file_put_contents($commitsPath, json_encode(array_reverse($fetched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                $commits = array_reverse($fetched);
            }
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
                <!-- Top summary removed as requested -->

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 mb-0 text-white">Derniers commits</h3>
                        <div>
                            <a class="btn btn-outline-light btn-sm" href="https://github.com/MeTtHa-Matt/Warriors-Training-Club-App" target="_blank" rel="noopener">
                                <i class="bi bi-github me-1"></i>Ouvrir le dépôt
                            </a>
                        </div>
                    </div>

                    <div class="info-card p-3">
                        <div id="commits-list" class="row g-3">
                            <p class="mb-0 text-white">Chargement initial des commits…</p>
                        </div>
                    </div>
                </div>

                <!-- Modal pour détails commit -->
                <div class="modal fade" id="commitModal" tabindex="-1" aria-labelledby="commitModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content bg-dark text-white">
                      <div class="modal-header">
                        <h5 class="modal-title" id="commitModalLabel">Détails commit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body" id="commitModalBody">
                        Chargement…
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php $jsPath = __DIR__ . '/js/commits-dashboard.js'; $v = is_file($jsPath) ? filemtime($jsPath) : time(); ?>
    <script src="js/commits-dashboard.js?v=<?= $v ?>"></script>
</body>

</html>
