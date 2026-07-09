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

$usersStmt = $pdo->query(
    'SELECT id, firstname, lastname, email, pdp, admin, gerer_seances, ban, maintenance
     FROM account_wtc
     ORDER BY lastname ASC, firstname ASC'
);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
$maintenanceEnabled = (bool) $pdo->query('SELECT MAX(maintenance) FROM account_wtc')->fetchColumn();

$pageTitle = "Warriors Training Club - Utilisateurs";