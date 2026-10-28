<?php
require_once __DIR__ . '/../includes/general/session-config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
$formatMessages = static function (array $messages): array {
    foreach ($messages as &$message) {
        $message['created_at_label'] = date('H:i · d/m/Y', strtotime($message['created_at']));
    }
    unset($message);
    return $messages;
};

$requestData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$reportId = (string) ($requestData['report'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $reportId)) {
    $respond(['error' => 'Dossier introuvable.'], 404);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($requestData['viewer_role'] ?? '') === 'admin'
    && $userId > 0
    && (int) ($_SESSION['admin'] ?? 0) === 1;
$deviceToken = (string) ($_COOKIE['wtc_report_device'] ?? '');
$deviceHash = preg_match('/^[a-f0-9]{64}$/', $deviceToken) ? hash('sha256', $deviceToken) : '';

try {
    if ($isAdmin) {
        $reportQuery = $pdo->prepare('SELECT id, status FROM dashboard_reports WHERE id = ?');
        $reportQuery->execute([$reportId]);
    } else {
        $reportQuery = $pdo->prepare(
            'SELECT id, status FROM dashboard_reports
             WHERE id = ? AND (
                 (? > 0 AND owner_user_id = ?)
                 OR (owner_user_id IS NULL AND device_hash = ?)
             )'
        );
        $reportQuery->execute([$reportId, $userId, $userId ?: -1, $deviceHash]);
    }

    $report = $reportQuery->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        $respond(['error' => 'Dossier introuvable.'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals((string) ($_SESSION['report_csrf'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
            $respond(['error' => 'La session a expiré. Recharge la page et réessaie.'], 403);
        }
        if ($report['status'] !== 'open') {
            $respond(['error' => 'Ce dossier est fermé.'], 409);
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        if (mb_strlen($message) < 1 || mb_strlen($message) > 4000) {
            $respond(['error' => 'Le message doit contenir entre 1 et 4 000 caractères.'], 422);
        }

        $senderType = $isAdmin ? 'admin' : 'user';
        $senderId = $userId > 0 ? $userId : null;
        $insert = $pdo->prepare(
            'INSERT INTO dashboard_report_messages (report_id, sender_type, sender_id, message)
             VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$reportId, $senderType, $senderId, $message]);

        $messageQuery = $pdo->prepare(
            'SELECT id, sender_type, message, created_at
             FROM dashboard_report_messages WHERE id = ? AND report_id = ?'
        );
        $messageQuery->execute([(int) $pdo->lastInsertId(), $reportId]);
        $newMessage = $messageQuery->fetch(PDO::FETCH_ASSOC);
        $respond(['message' => $formatMessages([$newMessage])[0], 'status' => $report['status']]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $respond(['error' => 'Méthode non autorisée.'], 405);
    }

    $afterId = filter_var($_GET['after'] ?? 0, FILTER_VALIDATE_INT);
    if ($afterId === false || $afterId < 0) {
        $respond(['error' => 'Curseur de conversation invalide.'], 422);
    }

    $messageQuery = $pdo->prepare(
        'SELECT id, sender_type, message, created_at
         FROM dashboard_report_messages
         WHERE report_id = ? AND id > ?
         ORDER BY id ASC LIMIT 100'
    );
    $messageQuery->execute([$reportId, $afterId]);
    $messages = $messageQuery->fetchAll(PDO::FETCH_ASSOC);

    if (!$isAdmin && $messages) {
        $lastMessageId = (int) end($messages)['id'];
        $lastAdminMessage = $pdo->prepare(
            "SELECT MAX(id) FROM dashboard_report_messages
             WHERE report_id = ? AND sender_type = 'admin' AND id <= ?"
        );
        $lastAdminMessage->execute([$reportId, $lastMessageId]);
        $lastViewedId = (int) $lastAdminMessage->fetchColumn();
        if ($lastViewedId > 0) {
            $markViewed = $pdo->prepare(
                'UPDATE dashboard_reports
                 SET user_last_viewed_message_id = GREATEST(user_last_viewed_message_id, ?)
                 WHERE id = ?'
            );
            $markViewed->execute([$lastViewedId, $reportId]);
        }
    }

    $respond([
        'messages' => $formatMessages($messages),
        'status' => $report['status'],
        'next' => $messages ? (int) end($messages)['id'] : $afterId,
    ]);
} catch (Throwable $error) {
    error_log('[report-conversation] ' . $error->getMessage());
    $respond(['error' => 'Impossible de mettre à jour cette conversation.'], 500);
}
