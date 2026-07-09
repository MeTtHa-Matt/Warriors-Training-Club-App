<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../general/db.php';
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

// Vérifier que la séance existe et appartient à l'utilisateur
$stmt = $pdo->prepare('SELECT id FROM seances WHERE id = ? AND created_by = ? LIMIT 1');
$stmt->execute([$id, $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'seance_not_found']);
    exit;
}

try {
    $deleteStmt = $pdo->prepare('DELETE FROM seances WHERE id = ? AND created_by = ?');
    $deleteStmt->execute([$id, $_SESSION['user_id']]);

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'delete_failed']);
    exit;
}
