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

$seanceId = isset($_GET['seance_id']) ? (int) $_GET['seance_id'] : 0;
if ($seanceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 100;
$offset = isset($_GET['offset']) ? max(0, min(100000, (int) $_GET['offset'])) : 0;

$stmt = $pdo->prepare(
    "SELECT i.id, i.firstname, i.lastname, i.account_id, i.created_at, i.inscrit_par,
            a.firstname AS par_firstname, a.lastname AS par_lastname
     FROM inscriptions_seances i
     LEFT JOIN account_wtc a ON a.id = i.inscrit_par
     WHERE i.seance_id = :seance_id
    ORDER BY i.created_at ASC
    LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':seance_id', $seanceId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$inscrits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasMore = count($inscrits) > $limit;
if ($hasMore) {
    array_pop($inscrits);
}

echo json_encode(['inscrits' => $inscrits, 'has_more' => $hasMore]);
