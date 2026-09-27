<?php
$errorStatus = (int) ($_SERVER['REDIRECT_STATUS'] ?? http_response_code());
if ($errorStatus < 400 || $errorStatus > 599) {
    $errorStatus = 500;
}
http_response_code($errorStatus);

$errorReference = $errorReference ?? ($_SERVER['WTC_ERROR_REFERENCE'] ?? strtoupper(bin2hex(random_bytes(4))));
$errorMessages = [
    400 => ['Requête invalide', 'La demande n’a pas pu être comprise. Vérifie les informations saisies puis réessaie.'],
    401 => ['Connexion nécessaire', 'Connecte-toi pour accéder à cette page.'],
    403 => ['Accès refusé', 'Tu n’as pas les droits nécessaires pour consulter cette page.'],
    404 => ['Page introuvable', 'Cette page n’existe peut-être plus ou son adresse a changé.'],
    429 => ['Trop de demandes', 'Tu as effectué trop d’actions en peu de temps. Patiente un instant puis réessaie.'],
    500 => ['Petit souci technique', 'La page rencontre un problème. Réessaie dans quelques instants.'],
    502 => ['Service momentanément indisponible', 'Le serveur met trop de temps à répondre. Réessaie dans quelques instants.'],
    503 => ['Service momentanément indisponible', 'Le site est temporairement indisponible. Réessaie un peu plus tard.'],
];
[$errorTitle, $errorDescription] = $errorMessages[$errorStatus] ?? $errorMessages[500];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?> — Warriors Training Club</title>
    <link rel="icon" type="image/png" href="img/wtc.png">
    <link rel="stylesheet" href="css/style.css?v=20260820">
    <style>
        .error-center { max-width: 720px; margin: 6rem auto; text-align: center; }
        .error-title { font-size: 2rem; margin-bottom: 1rem; }
        .error-desc { color: #6b7280; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar--site">
            <div class="container">
                <a class="navbar-brand" href="/">Warriors Training Club</a>
            </div>
        </nav>
    </header>

    <main class="error-center">
        <div class="hero hero--compact">
            <div class="container">
                <h1 class="error-title"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="error-desc"><?= htmlspecialchars($errorDescription, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($errorStatus >= 500): ?>
                    <p class="error-desc">Si le problème persiste, communique cette référence au club : <strong><?= htmlspecialchars($errorReference, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php endif; ?>
                <p>
                    <a class="btn btn-wtc-gold rounded-pill" href="/">Retour à l’accueil</a>
                </p>
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center mt-5 mb-4">
            <small>&copy; <?= date('Y') ?> Warriors Training Club</small>
        </div>
    </footer>

</body>
</html>
