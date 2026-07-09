<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/../general/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../inscription.php');
    exit;
}

$errors = [];

$old = [
    'firstname' => trim($_POST['firstname'] ?? ''),
    'lastname'  => trim($_POST['lastname'] ?? ''),
    'email'     => trim($_POST['email'] ?? ''),
];

$firstname       = $old['firstname'];
$lastname        = $old['lastname'];
$email           = $old['email'];
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($firstname === '' || mb_strlen($firstname) > 100) {
    $errors[] = "Le prénom est invalide.";
}
if ($lastname === '' || mb_strlen($lastname) > 150) {
    $errors[] = "Le nom est invalide.";
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    $errors[] = "L'adresse email est invalide.";
}
if (mb_strlen($password) < 8) {
    $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
}
if ($password !== $passwordConfirm) {
    $errors[] = "Les mots de passe ne correspondent pas.";
}

if (empty($errors)) {
    $stmt = $pdo->prepare('SELECT id FROM account_wtc WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        $errors[] = "Un compte existe déjà avec cette adresse email.";
    }
}

$pdpFilename = 'pdp_base.png';

if (empty($errors) && isset($_FILES['pdp']) && $_FILES['pdp']['error'] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['pdp']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'envoi de la photo de profil.";
    } else {
        $maxSize = 5 * 1024 * 1024; // 5 Mo
        if ($_FILES['pdp']['size'] > $maxSize) {
            $errors[] = "La photo de profil ne doit pas dépasser 5 Mo.";
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['pdp']['tmp_name']);

        if (!isset($allowedTypes[$mimeType])) {
            $errors[] = "Le format de la photo de profil doit être JPG, PNG ou WEBP.";
        }

        if (empty($errors)) {
            $extension = $allowedTypes[$mimeType];
            $uploadDir = __DIR__ . '/../../img/pdps/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            do {
                $pdpFilename = 'pdp_' . bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $uploadDir . $pdpFilename;
            } while (file_exists($destination));

            if (!move_uploaded_file($_FILES['pdp']['tmp_name'], $destination)) {
                $errors[] = "Impossible d'enregistrer la photo de profil.";
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old']    = $old;
    header('Location: ../../inscription.php');
    exit;
}

$existingColumns = [];
$columnsStmt = $pdo->query('SHOW COLUMNS FROM account_wtc');
foreach ($columnsStmt as $column) {
    $existingColumns[$column['Field']] = true;
}

if (!isset($existingColumns['email_verified'])) {
    $pdo->exec('ALTER TABLE account_wtc ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0');
}
if (!isset($existingColumns['verification_token'])) {
    $pdo->exec('ALTER TABLE account_wtc ADD COLUMN verification_token VARCHAR(255) DEFAULT NULL');
}
if (!isset($existingColumns['verification_token_expires'])) {
    $pdo->exec('ALTER TABLE account_wtc ADD COLUMN verification_token_expires DATETIME DEFAULT NULL');
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$verificationToken = bin2hex(random_bytes(32));
$verificationExpires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

$stmt = $pdo->prepare(
    'INSERT INTO account_wtc (firstname, lastname, email, password, pdp, email_verified, verification_token, verification_token_expires)
     VALUES (:firstname, :lastname, :email, :password, :pdp, :email_verified, :verification_token, :verification_token_expires)'
);
$stmt->execute([
    'firstname' => $firstname,
    'lastname'  => $lastname,
    'email'     => $email,
    'password'  => $hashedPassword,
    'pdp'       => $pdpFilename,
    'email_verified' => 0,
    'verification_token' => $verificationToken,
    'verification_token_expires' => $verificationExpires,
]);

$mailResult = sendVerificationEmail($email, $firstname, $verificationToken);

if (!empty($mailResult['success'])) {
    $_SESSION['success'] = "Ton compte a bien été créé. Vérifie ta boîte mail pour confirmer ton adresse email.";
} else {
    $_SESSION['errors'] = ["Ton compte a bien été créé, mais l'email de vérification n'a pas pu être envoyé. Contacte l'administrateur du club." ];
    header('Location: ../../inscription.php');
    exit;
}

header('Location: ../../connexion.php');
exit;
