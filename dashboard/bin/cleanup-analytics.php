<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';
define('WTC_DISABLE_SQL_ACTION_AUDIT', true);
$host = dashboardSetting('ANALYTICS_MAINTENANCE_DB_HOST');
$port = dashboardSetting('ANALYTICS_MAINTENANCE_DB_PORT', '3306');
$database = dashboardSetting('ANALYTICS_MAINTENANCE_DB_DATABASE');
$username = dashboardSetting('ANALYTICS_MAINTENANCE_DB_USERNAME');
$password = dashboardSetting('ANALYTICS_MAINTENANCE_DB_PASSWORD');
if ($host === '' || !ctype_digit($port) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $host)
    || !preg_match('/^[A-Za-z0-9_]+$/', $database) || $username === '' || $password === ''
) {
    fwrite(STDERR, "Analytics cleanup unavailable: maintenance database settings are incomplete.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]
);

$lock = (int) $pdo->query("SELECT GET_LOCK('wtc_analytics_retention', 0)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDOUT, "Analytics cleanup skipped: another cleanup is running.\n");
    exit(0);
}

try {
    $totalDeleted = 0;
    $delete = $pdo->prepare('DELETE FROM analytics_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY created_at ASC LIMIT 5000');
    for ($batch = 0; $batch < 20; $batch++) {
        $delete->execute();
        $deleted = $delete->rowCount();
        $totalDeleted += $deleted;
        if ($deleted < 5000) {
            break;
        }
    }
    fwrite(STDOUT, "Analytics cleanup deleted {$totalDeleted} expired events.\n");
} catch (Throwable $error) {
    error_log('[analytics-cleanup] ' . $error->getMessage());
    fwrite(STDERR, "Analytics cleanup failed. Check the server error log.\n");
    $exitCode = 1;
} finally {
    $pdo->query("SELECT RELEASE_LOCK('wtc_analytics_retention')");
}

if (isset($exitCode)) {
    exit($exitCode);
}
