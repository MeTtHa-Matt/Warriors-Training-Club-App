<?php

if (empty($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$currentId = $_SESSION['user_id'];

$adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
$adminCheckStmt->execute([$currentId]);
$isAdmin = (bool) $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_action'], $_POST['target_id'])) {

    $targetId = (int) $_POST['target_id'];
    $action = $_POST['toggle_action'];

    $allowedFields = ['admin', 'gerer_seances'];

    if (!in_array($action, $allowedFields, true)) {
        $errors[] = "Action inconnue.";
    } elseif ($targetId === (int) $currentId && $action === 'admin') {
        $errors[] = "Tu ne peux pas retirer tes propres droits administrateur.";
    } else {
        $currentValueStmt = $pdo->prepare("SELECT `$action` FROM account_wtc WHERE id = ?");
        $currentValueStmt->execute([$targetId]);
        $currentValue = $currentValueStmt->fetchColumn();

        if ($currentValue === false) {
            $errors[] = "Utilisateur introuvable.";
        } else {
            $newValue = $currentValue ? 0 : 1;
            $updateStmt = $pdo->prepare("UPDATE account_wtc SET `$action` = ? WHERE id = ?");
            $updateStmt->execute([$newValue, $targetId]);
            $success = "Le statut a bien été mis à jour.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email_action'], $_POST['target_id'])) {

    $targetId = (int) $_POST['target_id'];

    if ($targetId === (int) $currentId) {
        $errors[] = "Tu ne peux pas vérifier ton propre email.";
    } else {
        $userStmt = $pdo->prepare('SELECT id FROM account_wtc WHERE id = ?');
        $userStmt->execute([$targetId]);

        if ($userStmt->fetchColumn() === false) {
            $errors[] = "Utilisateur introuvable.";
        } else {
            $verifyStmt = $pdo->prepare('UPDATE account_wtc SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?');
            $verifyStmt->execute([$targetId]);
            $success = "L'email a bien été vérifié.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account_action'], $_POST['target_id'])) {

    $targetId = (int) $_POST['target_id'];

    if ($targetId === (int) $currentId) {
        $errors[] = "Tu ne peux pas supprimer ton propre compte.";
    } else {
        $userStmt = $pdo->prepare('SELECT id FROM account_wtc WHERE id = ?');
        $userStmt->execute([$targetId]);

        if ($userStmt->fetchColumn() === false) {
            $errors[] = "Utilisateur introuvable.";
        } else {
            $deleteStmt = $pdo->prepare('DELETE FROM account_wtc WHERE id = ?');
            $deleteStmt->execute([$targetId]);
            $success = "Le compte a bien été supprimé.";
        }
    }
}

$usersStmt = $pdo->query(
    'SELECT id, firstname, lastname, email, pdp, admin, gerer_seances, ban, maintenance, email_verified
     FROM account_wtc
     ORDER BY lastname ASC, firstname ASC'
);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
$maintenanceEnabled = (bool) $pdo->query('SELECT MAX(maintenance) FROM account_wtc')->fetchColumn();

$pageTitle = "Warriors Training Club - Utilisateurs";