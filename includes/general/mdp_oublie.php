<?php

$pageTitle = "Warriors Training Club - Mot de passe oublié";

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Merci d’entrer une adresse email valide.";
    } else {
        $stmt = $pdo->prepare('SELECT id, firstname, email FROM account_wtc WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            $success = "Si un compte existe pour cette adresse, un email de réinitialisation a été envoyé.";
        } else {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $updateStmt = $pdo->prepare('UPDATE account_wtc SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
            $updateStmt->execute([$token, $account['id']]);

            $baseUrl = getApplicationBaseUrl();
            $link = $baseUrl . '/reinitialiser-mot-de-passe.php?token=' . urlencode($token);
            $mailResult = sendPasswordResetEmail($account['email'], $account['firstname'], $link);

            if ($mailResult['success']) {
                $success = "Si un compte existe pour cette adresse, un email de réinitialisation a été envoyé.";
            } else {
                $errors[] = "Impossible d’envoyer l’email pour le moment. Merci de réessayer plus tard.";
            }
        }
    }
}