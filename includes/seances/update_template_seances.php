<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/template_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
if ((int) ($_SESSION['gerer_seances'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

ensureSeanceTemplateSchema($pdo);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$templateId = (int) ($input['template_id'] ?? 0);

if ($templateId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

// Verifier que le template existe et appartient a l'utilisateur
$stmt = $pdo->prepare('SELECT * FROM seance_templates WHERE id = ? AND created_by = ? LIMIT 1');
$stmt->execute([$templateId, $_SESSION['user_id']]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$template) {
    http_response_code(404);
    echo json_encode(['error' => 'template_not_found']);
    exit;
}

$sessions = json_decode($template['sessions'] ?? '[]', true) ?: [];

$today = (new DateTimeImmutable('today'))->setTime(0, 0);
$startDate = startOfMonth($today);
$endDate = (new DateTimeImmutable($startDate->format('Y-m-t')))->setTime(0, 0);

$dates = buildTemplateDates($startDate, $endDate, $sessions);

$deleteStmt = $pdo->prepare(
    'DELETE FROM seances WHERE template_id = ? AND created_by = ? AND is_modified = 0 AND date_seance BETWEEN ? AND ?'
);
$deleteStmt->execute([
    $templateId,
    $_SESSION['user_id'],
    $startDate->format('Y-m-d'),
    $endDate->format('Y-m-d'),
]);
$removed = $deleteStmt->rowCount();

$created = 0;
$insertStmt = $pdo->prepare(
    'INSERT INTO seances (date_seance, heure_debut, heure_fin, type_seance, coach, lieu_seance, lieu_rdv, description, created_by, template_id, is_modified)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
);
foreach ($dates as $dateInfo) {
    $insertStmt->execute([
        $dateInfo['date'],
        $dateInfo['heure_debut'] ?? '00:00:00',
        $dateInfo['heure_fin'] ?? '00:00:00',
        $dateInfo['type_seance'] ?? '',
        $dateInfo['coach'] ?? '',
        $dateInfo['lieu_seance'] ?? '',
        $dateInfo['lieu_rdv'] ?? '',
        $dateInfo['description'] ?? null,
        $_SESSION['user_id'],
        $templateId,
    ]);
    $created++;
}

echo json_encode(['success' => true, 'created' => $created, 'removed' => $removed]);
exit;
