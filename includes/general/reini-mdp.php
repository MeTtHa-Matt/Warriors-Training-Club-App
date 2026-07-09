<?php
$pageTitle = "Warriors Training Club - Réinitialiser mon mot de passe";

$errors = [];
$success = null;
$token = $_GET['token'] ?? '';

if ($token === '') {
    $errors[] = "Lien de réinitialisation invalide.";
} else {
    $stmt = $pdo->prepare('SELECT id FROM account_wtc WHERE password_reset_token = ? AND password_reset_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        $errors[] = "Ce lien est invalide ou a expiré.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($token === '') {
        $errors[] = "Lien de réinitialisation invalide.";
    }

    if (strlen($newPassword) < 8) {
        $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = "Les deux mots de passe ne correspondent pas.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM account_wtc WHERE password_reset_token = ? AND password_reset_expires > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            $errors[] = "Ce lien est invalide ou a expiré.";
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE account_wtc SET `password` = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?');
            $updateStmt->execute([$hash, $account['id']]);
            $success = "Ton mot de passe a bien été réinitialisé. Tu peux maintenant te connecter.";
        }
    }
}