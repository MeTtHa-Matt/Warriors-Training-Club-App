<?php
require_once __DIR__ . '/includes/general/session-config.php';
require_once __DIR__ . '/includes/general/db.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    $_SESSION['errors'] = ['Lien de vérification invalide.'];
    header('Location: connexion.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, email_verified, verification_token_expires FROM account_wtc WHERE verification_token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    $_SESSION['errors'] = ['Ce lien de vérification est invalide ou a déjà été utilisé.'];
    header('Location: connexion.php');
    exit;
}

if (!empty($account['verification_token_expires']) && strtotime($account['verification_token_expires']) < time()) {
    $_SESSION['errors'] = ['Ce lien de vérification a expiré. Demande un nouveau mail de vérification à l’administrateur.'];
    header('Location: connexion.php');
    exit;
}

$pdo->prepare('UPDATE account_wtc SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = :id')->execute(['id' => $account['id']]);

$_SESSION['success'] = 'Votre adresse email a bien été vérifiée. Vous pouvez maintenant vous connecter.';
header('Location: connexion.php');
exit;
