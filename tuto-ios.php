<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
include __DIR__ . "/includes/general/index-liens.php";

$tutorials = [
    [
        'title' => 'Étape 1',
        'image' => 'img/tuto ios/1.jpeg',
        'caption' => 'Appuyez sur le bouton de partage'
    ],
    [
        'title' => 'Étape 2',
        'image' => 'img/tuto ios/2.jpeg',
        'caption' => 'Appuyer sur « En savoir plus »'
    ],
    [
        'title' => 'Étape 3',
        'image' => 'img/tuto ios/3.jpeg',
        'caption' => 'Cliquez sur " + Sur l’écran d’accueil"'
    ],
    [
        'title' => 'Étape 4',
        'image' => 'img/tuto ios/4.jpeg',
        'caption' => 'Appuyez sur "Ajouter la web app"'
    ],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tutoriel iOS - Warriors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
</head>
<body>
<?php require 'includes/general/navbar.php'; ?>

<main class="section">
    <div class="container">
        <div class="section-head">
            <h1>Tutoriel d'installation — iOS</h1>
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
