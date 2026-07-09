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

$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 30;

$stmt = $pdo->prepare(
    "SELECT id, date_seance, heure_debut, heure_fin, type_seance, coach, lieu_seance
     FROM seances
     WHERE date_seance >= CURDATE()
     ORDER BY date_seance ASC, heure_debut ASC
     LIMIT $limit"
);
$stmt->execute();

echo json_encode(['seances' => $stmt->fetchAll()]);
