<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
include __DIR__ . "/includes/general/index-liens.php";

$tutorials = [
    [
        'title' => 'Étape 1',
        'image' => 'img/tuto android/1.jpg',
        'caption' => 'Cliquez sur les 3 petits points pour ouvrir le menu du navigateur'
    ],
    [
        'title' => 'Étape 2',
        'image' => 'img/tuto android/2.jpg',
        'caption' => 'Faire défiler jusqu’en bas pour trouver l’option d’installation'
    ],
    [
        'title' => 'Étape 3',
        'image' => 'img/tuto android/3.jpg',
        'caption' => 'Choisir l’option installer puis créer un raccourci'
    ],
    [
        'title' => 'Étape 4',
        'image' => 'img/tuto android/4.jpg',
        'caption' => 'Appuyer sur Installer pour installer la web app sur votre appareil'
    ],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tutoriel Android - Warriors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
</head>
<body>
<?php require 'includes/general/navbar.php'; ?>

<main class="section">
    <div class="container">
        <div class="section-head">
            <h1>Tutoriel d'installation — Android</h1>
            <p class="eyebrow">Suivez ces étapes</p>
        </div>

        <?php foreach($tutorials as $step): ?>
            <div class="card mb-3">
                <div class="row g-0">
                    <div class="col-12 col-md-4">
                        <img src="<?= htmlspecialchars($step['image']) ?>" class="img-fluid rounded-start" alt="<?= htmlspecialchars($step['title']) ?>">
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($step['title']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($step['caption']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</main>

<?php require 'includes/general/footer.php'; ?>

</body>
</html>
