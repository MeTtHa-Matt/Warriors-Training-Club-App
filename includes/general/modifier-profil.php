<?php

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$assetVersion = date('YmdHis');

if (empty($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$accountId = $_SESSION['user_id'];
$errors = [];
$success = null;

function cropAndResizeImage(string $sourcePath, string $destinationPath, string $mimeType, int $size = 400): bool
{
    $source = null;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/pjpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $source = @imagecreatefromjpeg($sourcePath);
            }
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) {
                $source = @imagecreatefrompng($sourcePath);
            }
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($sourcePath);
            }
            break;
    }

    if (!$source && function_exists('imagecreatefromstring')) {
        $data = @file_get_contents($sourcePath);
        if ($data !== false) {
            $source = @imagecreatefromstring($data);
        }
    }

    if (!$source) {
        return false;
    }

    $originalWidth = imagesx($source);
    $originalHeight = imagesy($source);

    $cropSize = min($originalWidth, $originalHeight);
    $cropX = (int) (($originalWidth - $cropSize) / 2);
    $cropY = (int) (($originalHeight - $cropSize) / 2);

    $destination = imagecreatetruecolor($size, $size);

    if ($mimeType === 'image/png') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }

    imagecopyresampled(
        $destination,
        $source,
        0,
        0,
        $cropX,
        $cropY,
        $size,
        $size,
        $cropSize,
        $cropSize
    );

    switch ($mimeType) {
        case 'image/jpeg':
            $saved = imagejpeg($destination, $destinationPath, 85);
            break;
        case 'image/png':
            $saved = imagepng($destination, $destinationPath);
            break;
        case 'image/webp':
            $saved = imagewebp($destination, $destinationPath, 85);
            break;
        default:
            $saved = false;
    }

    imagedestroy($source);
    imagedestroy($destination);

    return $saved;
}

$stmt = $pdo->prepare('SELECT id, firstname, lastname, pdp, accept_email FROM account_wtc WHERE id = ?');
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    header('Location: connexion.php');
    exit;
}

$pdpsDir = __DIR__ . '/../../img/pdps/';
$account['pdp'] = basename((string) ($account['pdp'] ?? '')) ?: 'pdp_base.png';
$accountPdpFile = $pdpsDir . $account['pdp'];
if (!file_exists($accountPdpFile) || !is_file($accountPdpFile)) {
    $account['pdp'] = 'pdp_base.png';
}

if (empty($_SESSION['pdp']) || !file_exists($pdpsDir . basename($_SESSION['pdp']))) {
    $_SESSION['pdp'] = $account['pdp'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_infos'])) {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');

    if ($firstname === '' || $lastname === '') {
        $errors[] = "Le prénom et le nom sont obligatoires.";
    }

    $pdpFilename = $account['pdp'];

    if (!empty($_FILES['pdp']['name']) && $_FILES['pdp']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $maxSize = 2 * 1024 * 1024; // 2 Mo

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($_FILES['pdp']['tmp_name']);
        $fileSize = $_FILES['pdp']['size'];
        $extension = strtolower(pathinfo($_FILES['pdp']['name'], PATHINFO_EXTENSION));

        if (!isset($allowedTypes[$fileType])) {
            $errors[] = 'Le format de l\'image doit être JPEG, PNG ou WebP.';
        } elseif ($fileSize > $maxSize) {
            $errors[] = 'L\'image ne doit pas dépasser 2 Mo.';
        } else {
            $extension = $allowedTypes[$fileType];
            $newFilename = 'pdp_' . $accountId . '.' . $extension;
            $pdpsDir = __DIR__ . '/../../img/pdps/';
            if (!is_dir($pdpsDir)) {
                mkdir($pdpsDir, 0755, true);
            }
            $destination = $pdpsDir . $newFilename;

            $canResize = extension_loaded('gd') && function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
            $saved = false;

            if ($canResize) {
                $saved = cropAndResizeImage($_FILES['pdp']['tmp_name'], $destination, $fileType, 400);
            }

            if (!$saved && !$canResize) {
                $saved = move_uploaded_file($_FILES['pdp']['tmp_name'], $destination);
            }

            if ($saved) {
                if (!empty($account['pdp']) && $account['pdp'] !== 'pdp_base.png' && $account['pdp'] !== $newFilename) {
                    $oldPath = $pdpsDir . $account['pdp'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $pdpFilename = $newFilename;
            } elseif (!$canResize) {
                $errors[] = 'Impossible d\'enregistrer la photo de profil.';
            } else {
                $errors[] = "Une erreur est survenue lors du traitement de l'image.";
            }
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare(
            'UPDATE account_wtc SET firstname = ?, lastname = ?, pdp = ? WHERE id = ?'
        );
        $updateStmt->execute([$firstname, $lastname, $pdpFilename, $accountId]);

        $account['firstname'] = $firstname;
        $account['lastname'] = $lastname;
        $account['pdp'] = $pdpFilename;

        $_SESSION['firstname'] = $firstname;
        $_SESSION['pdp'] = $pdpFilename;

        $success = "Tes informations ont bien été mises à jour.";
    }
}

$passwordSuccess = null;
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $pwdStmt = $pdo->prepare('SELECT `password` FROM account_wtc WHERE id = ?');
    $pwdStmt->execute([$accountId]);
    $currentHash = $pwdStmt->fetchColumn();

    if (!password_verify($currentPassword, $currentHash)) {
        $passwordErrors[] = "Le mot de passe actuel est incorrect.";
    }
    if (strlen($newPassword) < 8) {
        $passwordErrors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    }
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = "Les deux mots de passe ne correspondent pas.";
    }

    if (empty($passwordErrors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePwdStmt = $pdo->prepare('UPDATE account_wtc SET `password` = ? WHERE id = ?');
        $updatePwdStmt->execute([$newHash, $accountId]);

        $passwordSuccess = "Ton mot de passe a bien été modifié.";
    }
}

$deleteErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

    $deletePassword = $_POST['delete_password'] ?? '';

    $pwdStmt = $pdo->prepare('SELECT `password` FROM account_wtc WHERE id = ?');
    $pwdStmt->execute([$accountId]);
    $currentHash = $pwdStmt->fetchColumn();

    if ($deletePassword === '') {
        $deleteErrors[] = "Merci de saisir ton mot de passe pour confirmer la suppression.";
    } elseif (!password_verify($deletePassword, $currentHash)) {
        $deleteErrors[] = "Le mot de passe est incorrect.";
    }

    if (empty($deleteErrors)) {
        try {
            $pdo->beginTransaction();

            $detachStmt = $pdo->prepare('UPDATE inscriptions_seances SET account_id = NULL WHERE account_id = ?');
            $detachStmt->execute([$accountId]);

            $deleteStmt = $pdo->prepare('DELETE FROM account_wtc WHERE id = ?');
            $deleteStmt->execute([$accountId]);

            $pdo->commit();

            if (!empty($account['pdp']) && $account['pdp'] !== 'pdp_base.png') {
                $pdpsDir = __DIR__ . '/../../img/pdps/';
                $oldPath = $pdpsDir . $account['pdp'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }

            $_SESSION = [];
            session_destroy();

            header('Location: connexion.php?compte_supprime=1');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();

            if ($e->getCode() === '23000') {
                $deleteErrors[] = "Impossible de supprimer ton compte : il est associé à des séances existantes. Contacte un administrateur.";
            } else {
                $deleteErrors[] = "Une erreur est survenue lors de la suppression de ton compte.";
            }
        }
    }
}

$pageTitle = "Warriors Training Club - Modifier mon profil";