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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $seanceId = isset($_GET['seance_id']) ? (int) $_GET['seance_id'] : 0;
    if ($seanceId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, firstname, lastname, created_at
         FROM inscriptions_seances
         WHERE seance_id = :seance_id
           AND inscrit_par = :user_id
         ORDER BY created_at ASC'
    );
    $stmt->execute([
        'seance_id' => $seanceId,
        'user_id'   => $_SESSION['user_id'],
    ]);

    echo json_encode(['inscriptions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$inscriptionId = isset($input['inscription_id']) ? (int) $input['inscription_id'] : 0;

if ($inscriptionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_inscription_id']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, seance_id FROM inscriptions_seances WHERE id = :id AND inscrit_par = :user_id LIMIT 1'
);
$stmt->execute([
    'id' => $inscriptionId,
    'user_id' => $_SESSION['user_id'],
]);
$inscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inscription) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$deleteStmt = $pdo->prepare('DELETE FROM inscriptions_seances WHERE id = :id AND inscrit_par = :user_id');
$deleteStmt->execute([
    'id' => $inscriptionId,
    'user_id' => $_SESSION['user_id'],
]);

echo json_encode(['success' => true, 'seance_id' => (int) $inscription['seance_id']]);
