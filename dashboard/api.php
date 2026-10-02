<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
dashboardSendHeaders();
dashboardStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    dashboardJson(['error' => 'Méthode non autorisée.'], 405);
}

dashboardRequireAuth();
session_write_close();

$requestedPeriod = filter_var($_GET['period'] ?? 28, FILTER_VALIDATE_INT);
$periodDays = in_array($requestedPeriod, [7, 28, 90], true) ? $requestedPeriod : 28;
$databaseConfigured = dashboardSetting('DB_HOST') !== '' && dashboardSetting('DB_DATABASE') !== '' && dashboardSetting('DB_USERNAME') !== '';
$analyticsStatus = '';
$analytics = dashboardAnalyticsReport($analyticsStatus, $periodDays);

dashboardJson([
    'generatedAt' => gmdate(DATE_ATOM),
    'periodDays' => $periodDays,
    'analytics' => $analytics,
    'analyticsStatus' => $analyticsStatus,
    'site' => dashboardSiteMetrics(),
    'sites' => [[
        'id' => 'warriors',
        'name' => 'Warriors Training Club',
        'url' => 'Site Warriors',
        'connected' => $analytics !== null,
    ]],
    'sources' => [
        'analyticsConfigured' => $analytics !== null,
        'databaseConfigured' => $databaseConfigured,
    ],
]);