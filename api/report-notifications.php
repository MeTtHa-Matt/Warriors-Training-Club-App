<?php
require_once __DIR__ . '/../includes/general/session-config.php';
require_once __DIR__ . '/../includes/general/report-schema.php';
header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$deviceToken = (string) ($_COOKIE['wtc_report_device'] ?? '');
$deviceHash = preg_match('/^[a-f0-9]{64}$/', $deviceToken) ? hash('sha256', $deviceToken) : '';
if ($userId === 0 && $deviceHash === '') {
    echo json_encode(['user' => 0, 'admin' => 0]);
    exit;
}

try {
    ensureReportSchema($pdo);
    $userNotifications = $pdo->prepare(
        'SELECT COUNT(*) FROM dashboard_report_messages m
         INNER JOIN dashboard_reports r ON r.id = m.report_id
         WHERE m.sender_type = \'admin\' AND m.id > r.user_last_viewed_message_id
           AND (
               (? > 0 AND r.owner_user_id = ?)
               OR (r.owner_user_id IS NULL AND r.device_hash = ?)
           )'
    );
    $userNotifications->execute([$userId, $userId ?: -1, $deviceHash]);

    $adminNotifications = 0;
    if ((int) ($_SESSION['admin'] ?? 0) === 1) {
        $adminNotifications = (int) $pdo->query(
            "SELECT COUNT(*) FROM dashboard_reports r
             WHERE r.status = 'open' AND NOT EXISTS (
                 SELECT 1 FROM dashboard_report_messages m
                 WHERE m.report_id = r.id AND m.sender_type = 'admin'
                   AND m.id > COALESCE((
                       SELECT MAX(previous.id) FROM dashboard_report_messages previous
                       WHERE previous.report_id = r.id AND previous.sender_type = 'user'
                   ), 0)
             )"
        )->fetchColumn();
    }

    echo json_encode([
        'user' => (int) $userNotifications->fetchColumn(),
        'admin' => $adminNotifications,
    ]);
} catch (Throwable $error) {
    error_log('[report-notifications] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de charger les notifications des signalements.']);
}
