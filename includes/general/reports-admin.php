<?php
require_once __DIR__ . '/session-config.php';
require_once __DIR__ . '/verifications.php';

if (empty($_SESSION['user_id']) || (int) ($_SESSION['admin'] ?? 0) !== 1) {
    header('Location: index.php');
    exit;
}

$reportFile = __DIR__ . '/../../data/reports.json';
$reports = [];
if (is_file($reportFile)) {
    $decodedReports = json_decode(file_get_contents($reportFile) ?: '[]', true);
    if (is_array($decodedReports)) {
        $reports = $decodedReports;
    }
}
usort($reports, static function (array $first, array $second): int {
    return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
});

$pageTitle = 'Warriors Training Club - Signalements';
