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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput !== '' && $rawInput !== false) {
    $decodedInput = json_decode($rawInput, true);
    if (is_array($decodedInput)) {
        $input = $decodedInput;
    }
}

if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

$errors = [];

$date = trim($input['date_seance'] ?? '');
$heureDebut = trim($input['heure_debut'] ?? '');
$heureFin = trim($input['heure_fin'] ?? '');
$type = trim($input['type_seance'] ?? '');
$coach = trim($input['coach'] ?? '');
$lieuSeance = trim($input['lieu_seance'] ?? '');
$lieuRdv = trim($input['lieu_rdv'] ?? '');
$description = trim($input['description'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    $errors[] = "La date est invalide.";
}
if (!preg_match('/^\d{2}:\d{2}$/', $heureDebut)) {
    $errors[] = "L'heure de début est invalide.";
}
if (!preg_match('/^\d{2}:\d{2}$/', $heureFin)) {
    $errors[] = "L'heure de fin est invalide.";
}
if (empty($errors) && $heureFin <= $heureDebut) {
    $errors[] = "L'heure de fin doit être après l'heure de début.";
}
if ($type === '' || mb_strlen($type) > 100) {
    $errors[] = "Le type de séance est invalide.";
}
if ($coach === '' || mb_strlen($coach) > 150) {
    $errors[] = "Le coach est invalide.";
}
if ($lieuSeance === '' || mb_strlen($lieuSeance) > 150) {
    $errors[] = "Le lieu de la séance est invalide.";
}
if ($lieuRdv === '' || mb_strlen($lieuRdv) > 150) {
    $errors[] = "Le lieu de rendez-vous est invalide.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input', 'messages' => $errors]);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO seances (date_seance, heure_debut, heure_fin, type_seance, coach, lieu_seance, lieu_rdv, description, created_by)
     VALUES (:date_seance, :heure_debut, :heure_fin, :type_seance, :coach, :lieu_seance, :lieu_rdv, :description, :created_by)'
);
$stmt->execute([
    'date_seance' => $date,
    'heure_debut' => $heureDebut . ':00',
    'heure_fin' => $heureFin . ':00',
    'type_seance' => $type,
    'coach' => $coach,
    'lieu_seance' => $lieuSeance,
    'lieu_rdv' => $lieuRdv,
    'description' => $description !== '' ? $description : null,
    'created_by' => $_SESSION['user_id'],
]);

echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
