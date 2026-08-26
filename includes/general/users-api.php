<?php
require_once __DIR__ . "/session-config.php";
require_once __DIR__ . "/db.php";

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Non authentifié', 'authenticated' => false]);
    exit;
}

$currentId = (int) $_SESSION['user_id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '[]', true);
if (!is_array($data)) {
    $data = [];
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide']);
    exit;
}

$action = $data['action'] ?? null;
$targetId = (int) ($data['target_id'] ?? 0);

if (($action ?? null) === 'heartbeat') {
    $heartbeatStmt = $pdo->prepare('UPDATE account_wtc SET last_seen = NOW() WHERE id = ?');
    $heartbeatStmt->execute([$currentId]);
    echo json_encode(['success' => true, 'heartbeat' => true]);
    exit;
}

if (($action ?? null) === 'mark_offline') {
    $offlineStmt = $pdo->prepare('UPDATE account_wtc SET last_seen = DATE_SUB(NOW(), INTERVAL 301 SECOND) WHERE id = ?');
    $offlineStmt->execute([$currentId]);
    echo json_encode(['success' => true, 'offline' => true]);
    exit;
}

// Vérifier que l'utilisateur est admin
$adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
$adminCheckStmt->execute([$currentId]);
$isAdmin = (bool) $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé', 'authenticated' => true, 'admin' => false]);
    exit;
}

if (!$action) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

if ($action !== 'get_online_statuses' && $targetId <= 0) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

if ($action === 'get_online_statuses') {
    $onlineStmt = $pdo->query(
        'SELECT id
         FROM account_wtc
         WHERE last_seen IS NOT NULL
           AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= 300'
    );

    $onlineIds = [];
    foreach ($onlineStmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        $onlineIds[(int) $userId] = true;
    }

    echo json_encode(['success' => true, 'online_ids' => $onlineIds]);
    exit;
}

// Vérifier que l'utilisateur existe pour les actions qui ciblent un compte
$userStmt = $pdo->prepare('SELECT id FROM account_wtc WHERE id = ?');
$userStmt->execute([$targetId]);
if ($userStmt->fetchColumn() === false) {
    http_response_code(200);
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

if ($action === 'toggle_admin') {
    if ($targetId === (int) $currentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tu ne peux pas modifier tes propres droits']);
        exit;
    }

    $currentValueStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
    $currentValueStmt->execute([$targetId]);
    $currentValue = (int) $currentValueStmt->fetchColumn();
    $newValue = $currentValue ? 0 : 1;

    // Prevent removing the last admin
    if ($newValue === 0) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE admin = 1');
        $adminCount = (int) $countStmt->fetchColumn();
        if ($adminCount <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Impossible de retirer les droits : il doit rester au moins un administrateur.']);
            exit;
        }
    }

    $updateStmt = $pdo->prepare('UPDATE account_wtc SET admin = ? WHERE id = ?');
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

http_response_code(200);
echo json_encode(['success' => false, 'message' => 'Action inconnue']);
