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

$year  = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$start = sprintf('%04d-%02d-01', $year, $month);
$end   = date('Y-m-t', strtotime($start));

$stmt = $pdo->prepare(
    'SELECT id, date_seance, heure_debut, heure_fin, type_seance, coach
     FROM seances
     WHERE date_seance BETWEEN :start AND :end
     ORDER BY date_seance ASC, heure_debut ASC'
);
$stmt->execute(['start' => $start, 'end' => $end]);

echo json_encode([
    'year'    => $year,
    'month'   => $month,
    'seances' => $stmt->fetchAll(),
]);
