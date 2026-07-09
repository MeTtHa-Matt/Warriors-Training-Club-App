<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/verifications.php';
require __DIR__ . '/../general/db.php'; 

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload) || !array_key_exists('accept_email', $payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}

$acceptEmail = $payload['accept_email'];

if ($acceptEmail !== 0 && $acceptEmail !== 1 && $acceptEmail !== '0' && $acceptEmail !== '1') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valeur invalide.']);
    exit;
}

$acceptEmail = (int) $acceptEmail;
$accountId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('UPDATE account_wtc SET accept_email = ? WHERE id = ?');
    $stmt->execute([$acceptEmail, $accountId]);

    echo json_encode(['success' => true, 'accept_email' => $acceptEmail]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.']);
}
