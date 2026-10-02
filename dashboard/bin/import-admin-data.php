<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';
$pdo = dashboardDatabase();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Connexion DB dashboard indisponible.\n");
    exit(1);
}

$dataDirectory = dirname(__DIR__, 2) . '/data';
$reportCount = 0;
$commitCount = 0;

$reportFile = $dataDirectory . '/reports.json';
if (is_file($reportFile)) {
    $reports = json_decode((string) file_get_contents($reportFile), true);
    if (!is_array($reports)) {
        fwrite(STDERR, "Le fichier reports.json n’est pas un JSON valide.\n");
        exit(1);
    }
    $saveReport = $pdo->prepare('INSERT INTO dashboard_reports (id, email, message, created_at, device_hash, ip_hash) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE email = VALUES(email), message = VALUES(message), created_at = VALUES(created_at), device_hash = VALUES(device_hash), ip_hash = VALUES(ip_hash)');
    foreach ($reports as $report) {
        if (!is_array($report) || trim((string) ($report['email'] ?? '')) === '' || !isset($report['message'])) {
            continue;
        }
        $oldId = (string) ($report['id'] ?? '');
        $id = preg_match('/^[a-f0-9]{32}$/i', $oldId) ? strtolower($oldId) : md5($oldId . '|' . ($report['created_at'] ?? '') . '|' . $report['email']);
        $created = strtotime((string) ($report['created_at'] ?? ''));
        $saveReport->execute([
            $id,
            mb_substr((string) $report['email'], 0, 254),
            (string) $report['message'],
            gmdate('Y-m-d H:i:s', $created === false ? time() : $created),
            preg_match('/^[a-f0-9]{64}$/i', (string) ($report['device_hash'] ?? '')) ? strtolower($report['device_hash']) : null,
            preg_match('/^[a-f0-9]{64}$/i', (string) ($report['ip_hash'] ?? '')) ? strtolower($report['ip_hash']) : null,
        ]);
        $reportCount++;
    }
}

$commitFile = $dataDirectory . '/commits.json';
if (is_file($commitFile)) {
    $commits = json_decode((string) file_get_contents($commitFile), true);
    if (!is_array($commits)) {
        fwrite(STDERR, "Le fichier commits.json n’est pas un JSON valide.\n");
        exit(1);
    }
    $saveCommit = $pdo->prepare('INSERT INTO dashboard_commits (sha, message, url, author, committed_at, repo) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE message = VALUES(message), url = VALUES(url), author = VALUES(author), committed_at = VALUES(committed_at), repo = VALUES(repo)');
    foreach ($commits as $commit) {
        $sha = strtolower((string) ($commit['id'] ?? $commit['sha'] ?? ''));
        $timestamp = strtotime((string) ($commit['timestamp'] ?? $commit['date'] ?? ''));
        if (!preg_match('/^[a-f0-9]{40}$/', $sha) || $timestamp === false) {
            continue;
        }
        $saveCommit->execute([
            $sha,
            (string) ($commit['message'] ?? ''),
            mb_substr((string) ($commit['url'] ?? $commit['html_url'] ?? ''), 0, 500),
            mb_substr((string) ($commit['author'] ?? ''), 0, 190),
            gmdate('Y-m-d H:i:s', $timestamp),
            mb_substr((string) ($commit['repo'] ?? 'MeTtHa-Matt/Warriors-Training-Club-App'), 0, 190),
        ]);
        $commitCount++;
    }
}

printf("Import terminé : %d signalement(s), %d commit(s).\n", $reportCount, $commitCount);