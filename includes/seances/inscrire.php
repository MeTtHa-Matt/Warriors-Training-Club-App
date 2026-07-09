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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$seanceId = isset($input['seance_id']) ? (int) $input['seance_id'] : 0;
$mode = $input['mode'] ?? '';

if ($seanceId <= 0 || !in_array($mode, ['self', 'other'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

$stmt = $pdo->prepare('SELECT date_seance, heure_debut FROM seances WHERE id = :id');
$stmt->execute(['id' => $seanceId]);
$seance = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$seance) {
    http_response_code(404);
    echo json_encode(['error' => 'seance_not_found']);
    exit;
}

$startDateTime = new DateTimeImmutable($seance['date_seance'] . ' ' . $seance['heure_debut']);
if (new DateTimeImmutable('now') >= $startDateTime) {
    http_response_code(403);
    echo json_encode(['error' => 'registration_closed']);
    exit;
}

if ($mode === 'self') {
    $firstname = $_SESSION['firstname'];
    $lastname = $_SESSION['lastname'];
    $accountId = $_SESSION['user_id'];

    $check = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM inscriptions_seances
         WHERE seance_id = :seance_id
           AND LOWER(TRIM(firstname)) = LOWER(TRIM(:firstname))
           AND LOWER(TRIM(lastname)) = LOWER(TRIM(:lastname))'
    );
    $check->execute(['seance_id' => $seanceId, 'firstname' => $firstname, 'lastname' => $lastname]);
    if ((int) $check->fetch()['c'] > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'already_registered']);
        exit;
    }
} else {
    $firstname = trim($input['firstname'] ?? '');
    $lastname = trim($input['lastname'] ?? '');
    $accountId = null;

    if ($firstname === '' || mb_strlen($firstname) > 100 || $lastname === '' || mb_strlen($lastname) > 150) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_name']);
        exit;
    }
}

$stmt = $pdo->prepare(
    'INSERT INTO inscriptions_seances (seance_id, firstname, lastname, account_id, inscrit_par)
     VALUES (:seance_id, :firstname, :lastname, :account_id, :inscrit_par)'
);
$stmt->execute([
    'seance_id' => $seanceId,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'account_id' => $accountId,
    'inscrit_par' => $_SESSION['user_id'],
]);

echo json_encode(['success' => true]);
