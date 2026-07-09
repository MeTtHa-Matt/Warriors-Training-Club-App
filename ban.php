<?php
require_once __DIR__ . '/includes/general/session-config.php';
require_once __DIR__ . '/includes/general/db.php';

include __DIR__ . "/includes/general/ban.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Compte banni</title>
    <link rel="stylesheet" href="css/style.css?v=202607051">
</head>
<body>
<section class="section auth-section">
    <div class="container">
        <div class="auth-wrapper text-center">
            <h1>Compte banni</h1>
            <p>Ton compte a été banni. Si tu penses qu'il s'agit d'une erreur, contacte un administrateur.</p>
            <a href="includes/account/deconnexion_process.php" class="btn btn-wtc-gold rounded-pill">Se déconnecter</a>
        </div>
    </div>
</section>
<?php require 'includes/general/footer.php'; ?>
</body>
</html>
