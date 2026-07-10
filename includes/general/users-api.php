<?php
require_once __DIR__ . "/session-config.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$currentId = $_SESSION['user_id'];

// Vérifier que l'utilisateur est admin
$adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
$adminCheckStmt->execute([$currentId]);
$isAdmin = (bool) $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;
$targetId = (int) ($data['target_id'] ?? 0);

if (!$action || $targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

// Vérifier que l'utilisateur existe
$userStmt = $pdo->prepare('SELECT id FROM account_wtc WHERE id = ?');
$userStmt->execute([$targetId]);
if ($userStmt->fetchColumn() === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
    exit;
}

if ($action === 'toggle_gerer_seances') {
    if ($targetId === (int) $currentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tu ne peux pas modifier tes propres droits']);
        exit;
    }

    $currentValueStmt = $pdo->prepare('SELECT gerer_seances FROM account_wtc WHERE id = ?');
    $currentValueStmt->execute([$targetId]);
    $currentValue = (int) $currentValueStmt->fetchColumn();
    $newValue = $currentValue ? 0 : 1;

    $updateStmt = $pdo->prepare('UPDATE account_wtc SET gerer_seances = ? WHERE id = ?');
    $updateStmt->execute([$newValue, $targetId]);

    echo json_encode(['success' => true, 'message' => 'Statut mis à jour', 'new_value' => $newValue]);
    exit;
}

if ($action === 'toggle_ban') {
    if ($targetId === (int) $currentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tu ne peux pas te bannir toi-même']);
        exit;
    }

    $currentValueStmt = $pdo->prepare('SELECT ban FROM account_wtc WHERE id = ?');
    $currentValueStmt->execute([$targetId]);
    $currentValue = (int) $currentValueStmt->fetchColumn();
    $newValue = $currentValue ? 0 : 1;

    $updateStmt = $pdo->prepare('UPDATE account_wtc SET ban = ? WHERE id = ?');
    $updateStmt->execute([$newValue, $targetId]);

    echo json_encode(['success' => true, 'message' => 'Statut mis à jour', 'new_value' => $newValue]);
    exit;
}

if ($action === 'verify_email') {
    if ($targetId === (int) $currentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tu ne peux pas vérifier ton propre email']);
        exit;
    }

    $verifyStmt = $pdo->prepare('UPDATE account_wtc SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?');
    $verifyStmt->execute([$targetId]);

    echo json_encode(['success' => true, 'message' => 'Email vérifié']);
    exit;
}

if ($action === 'delete_account') {
    if ($targetId === (int) $currentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tu ne peux pas supprimer ton propre compte']);
        exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM account_wtc WHERE id = ?');
    $deleteStmt->execute([$targetId]);

    echo json_encode(['success' => true, 'message' => 'Compte supprimé']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action inconnue']);
