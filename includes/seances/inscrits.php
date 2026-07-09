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

$seanceId = isset($_GET['seance_id']) ? (int) $_GET['seance_id'] : 0;
if ($seanceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT i.firstname, i.lastname, i.account_id, i.created_at,
            a.firstname AS par_firstname, a.lastname AS par_lastname
     FROM inscriptions_seances i
     LEFT JOIN account_wtc a ON a.id = i.inscrit_par
     WHERE i.seance_id = :seance_id
     ORDER BY i.created_at ASC"
);
$stmt->execute(['seance_id' => $seanceId]);

echo json_encode(['inscrits' => $stmt->fetchAll()]);
