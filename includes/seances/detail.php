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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM seances WHERE id = :id');
$stmt->execute(['id' => $id]);
$seance = $stmt->fetch();

if (!$seance) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$startDateTime = new DateTimeImmutable($seance['date_seance'] . ' ' . $seance['heure_debut']);
$registrationAllowed = new DateTimeImmutable('now') < $startDateTime;

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS c FROM inscriptions_seances
     WHERE seance_id = :seance_id
       AND LOWER(TRIM(firstname)) = LOWER(TRIM(:firstname))
       AND LOWER(TRIM(lastname)) = LOWER(TRIM(:lastname))'
);
$stmt->execute([
    'seance_id' => $id,
    'firstname' => $_SESSION['firstname'],
    'lastname' => $_SESSION['lastname'],
]);
$isRegistered = (int) $stmt->fetch()['c'] > 0;

echo json_encode([
    'seance' => $seance,
    'can_manage' => (int) ($_SESSION['gerer_seances'] ?? 0) === 1,
    'is_registered' => $isRegistered,
    'registration_allowed' => $registrationAllowed,
]);
