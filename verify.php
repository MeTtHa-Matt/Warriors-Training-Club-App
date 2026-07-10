<?php
require_once __DIR__ . '/includes/general/session-config.php';
require_once __DIR__ . '/includes/general/db.php';

$cleanupStmt = $pdo->prepare(
    'DELETE FROM account_wtc
     WHERE email_verified = 0
       AND verification_token IS NOT NULL
       AND verification_token_expires IS NOT NULL
       AND verification_token_expires < NOW()'
);
$cleanupStmt->execute();

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
    $deleteStmt = $pdo->prepare('DELETE FROM account_wtc WHERE id = :id');
    $deleteStmt->execute(['id' => $account['id']]);

    $_SESSION['errors'] = ['Ce lien de vérification a expiré. Le compte associé a été supprimé, tu peux en créer un nouveau.'];
    header('Location: inscription.php');
    exit;
}

$pdo->prepare('UPDATE account_wtc SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = :id')->execute(['id' => $account['id']]);

$_SESSION['success'] = 'Votre adresse email a bien été vérifiée. Vous pouvez maintenant vous connecter.';
header('Location: connexion.php');
exit;

