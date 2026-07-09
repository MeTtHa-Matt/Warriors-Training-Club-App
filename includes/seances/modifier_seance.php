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

$dateSeance = $input['date_seance'] ?? null;
$heureDebut = $input['heure_debut'] ?? null;
$heureFin = $input['heure_fin'] ?? null;
$typeSeance = trim($input['type_seance'] ?? '');
$coach = trim($input['coach'] ?? '');
$lieuSeance = trim($input['lieu_seance'] ?? '');
$lieuRdv = trim($input['lieu_rdv'] ?? '');
$description = trim($input['description'] ?? '');

if (!$dateSeance || !$heureDebut || !$heureFin || !$typeSeance) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input', 'messages' => ['Tous les champs obligatoires doivent être remplis.']]);
    exit;
}

// Valider la date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateSeance)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_date']);
    exit;
}

// Valider les heures
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $heureDebut) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $heureFin)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_time']);
    exit;
}

try {
    $updateStmt = $pdo->prepare(
        'UPDATE seances 
         SET date_seance = :date_seance, heure_debut = :heure_debut, heure_fin = :heure_fin, 
             type_seance = :type_seance, coach = :coach, lieu_seance = :lieu_seance, 
             lieu_rdv = :lieu_rdv, description = :description, is_modified = 1
         WHERE id = :id AND created_by = :created_by'
    );

    $updateStmt->execute([
        'date_seance' => $dateSeance,
        'heure_debut' => $heureDebut,
        'heure_fin' => $heureFin,
        'type_seance' => $typeSeance,
        'coach' => $coach,
        'lieu_seance' => $lieuSeance,
        'lieu_rdv' => $lieuRdv,
        'description' => $description ?: null,
        'id' => $id,
        'created_by' => $_SESSION['user_id'],
    ]);

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'update_failed']);
    exit;
}
