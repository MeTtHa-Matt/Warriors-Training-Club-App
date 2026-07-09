<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/../general/persistent-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../connexion.php');
    exit;
}

$errors = [];

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Adresse email invalide.";
}
if ($password === '') {
    $errors[] = "Merci de renseigner ton mot de passe.";
}

$account = null;

if (empty($errors)) {
    $stmt = $pdo->prepare('SELECT * FROM account_wtc WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($password, $account['password'])) {
        $errors[] = "Identifiants incorrects.";
    } elseif ((int) $account['ban'] === 1) {
        $errors[] = "Ce compte a été banni. Contacte un administrateur du club.";
    } elseif ((int) $account['email_verified'] !== 1) {
        $errors[] = "Confirme ton adresse email avant de te connecter. Vérifie ta boîte mail.";
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old']    = ['email' => $email];
    header('Location: ../../connexion.php');
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id']   = $account['id'];
$_SESSION['firstname'] = $account['firstname'];
$_SESSION['lastname']  = $account['lastname'];
$_SESSION['email']     = $account['email'];
$_SESSION['pdp']       = $account['pdp'];
$_SESSION['admin']     = (int) $account['admin'];
$_SESSION['gerer_seances'] = (int) $account['gerer_seances'];
$_SESSION['ban']       = (int) $account['ban'];
$_SESSION['maintenance'] = (int) $account['maintenance'];

$tokenManager = new PersistentToken($pdo);
$tokenManager->create($account['id']);

header('Location: ../../index.php');
exit;
