<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../modifier-profil.php');
    exit;
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../../connexion.php');
    exit;
}

$accountId = $_SESSION['user_id'];
$errors = [];
$old = [];

$stmt = $pdo->prepare('SELECT firstname, lastname, email, pdp FROM account_wtc WHERE id = ?');
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    header('Location: ../../connexion.php');
    exit;
}

if (isset($_POST['update_infos'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $old = [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
    ];

    if ($firstname === '' || mb_strlen($firstname) > 100) {
        $errors[] = 'Le prénom est invalide.';
    }
    if ($lastname === '' || mb_strlen($lastname) > 150) {
        $errors[] = 'Le nom est invalide.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors[] = 'L\'adresse email est invalide.';
    }

    if (empty($errors)) {
        $checkStmt = $pdo->prepare('SELECT id FROM account_wtc WHERE email = ? AND id != ?');
        $checkStmt->execute([$email, $accountId]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Cette adresse email est déjà utilisée par un autre compte.';
        }
    }

    $pdpFilename = $account['pdp'];
    if (!empty($_FILES['pdp']['name']) && $_FILES['pdp']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['pdp']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erreur lors de l\'envoi de la photo de profil.';
        } else {
            $maxSize = 2 * 1024 * 1024;
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['pdp']['tmp_name']);
            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Le format de la photo de profil doit être JPG, PNG ou WEBP.';
            }
            if ($_FILES['pdp']['size'] > $maxSize) {
                $errors[] = 'La photo de profil ne doit pas dépasser 2 Mo.';
            }

            if (empty($errors)) {
                $extension = $allowedTypes[$mimeType];
                $pdpsDir = __DIR__ . '/../../img/pdps/';
                if (!is_dir($pdpsDir)) {
                    mkdir($pdpsDir, 0755, true);
                }
                $newFilename = 'pdp_' . $accountId . '_' . time() . '.' . $extension;
                $destination = $pdpsDir . $newFilename;
                if (!move_uploaded_file($_FILES['pdp']['tmp_name'], $destination)) {
                    $errors[] = 'Impossible d\'enregistrer la photo de profil.';
                } else {
                    if ($account['pdp'] !== 'pdp_base.png') {
                        $oldPath = $pdpsDir . $account['pdp'];
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $pdpFilename = $newFilename;
                }
            }
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare(
            'UPDATE account_wtc SET firstname = ?, lastname = ?, email = ?, pdp = ? WHERE id = ?'
        );
        $updateStmt->execute([$firstname, $lastname, $email, $pdpFilename, $accountId]);

        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
        $_SESSION['email'] = $email;
        $_SESSION['pdp'] = $pdpFilename;
        $_SESSION['success'] = 'Tes informations ont bien été mises à jour.';
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $old;
    }
}

if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $pwdStmt = $pdo->prepare('SELECT `password` FROM account_wtc WHERE id = ?');
    $pwdStmt->execute([$accountId]);
    $currentHash = $pwdStmt->fetchColumn();

    if (!password_verify($currentPassword, $currentHash)) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (empty($errors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePwdStmt = $pdo->prepare('UPDATE account_wtc SET `password` = ? WHERE id = ?');
        $updatePwdStmt->execute([$newHash, $accountId]);
        $_SESSION['password_success'] = 'Ton mot de passe a bien été modifié.';
    } else {
        $_SESSION['password_errors'] = $errors;
    }
}

header('Location: ../../modifier-profil.php');
exit;
