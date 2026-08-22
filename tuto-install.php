<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
include __DIR__ . "/includes/general/index-liens.php";

$tutorials = [
    'ios' => [
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
    ],
    'android' => [
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
    ],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Comment installer la web app - Warriors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
</head>
<body>
<?php require 'includes/general/navbar.php'; ?>

<main class="section">
    <div class="container">
        <div class="section-head">
            <h1>Comment installer la web app</h1>
            <p class="eyebrow">Guide d'installation</p>
        </div>

        <div class="d-flex gap-3 mb-4">
            <button id="show-ios" class="btn btn-wtc-outline">Comment l'installer sur iOS</button>
            <button id="show-android" class="btn btn-wtc-outline">Comment l'installer sur Android</button>
        </div>

        <div id="tutorial-target"></div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require 'includes/general/footer.php'; ?>

<script>
const tutorials = <?php echo json_encode($tutorials, JSON_UNESCAPED_UNICODE); ?>;

function renderTutorial(kind) {
    const target = document.getElementById('tutorial-target');
    target.innerHTML = '';
    const items = tutorials[kind] || [];
    items.forEach(function(it){
        const card = document.createElement('div');
        card.className = 'card mb-3';
        card.innerHTML = `
            <div class="row g-0">
                <div class="col-12 col-md-4">
                    <img src="${it.image}" class="img-fluid rounded-start" alt="${it.title}">
                </div>
                <div class="col-12 col-md-8">
                    <div class="card-body">
                        <h5 class="card-title">${it.title}</h5>
                        <p class="card-text">${it.caption}</p>
                    </div>
                </div>
            </div>
        `;
        target.appendChild(card);
    });
}

document.getElementById('show-ios').addEventListener('click', function(){ renderTutorial('ios'); });
document.getElementById('show-android').addEventListener('click', function(){ renderTutorial('android'); });
</script>

</body>
</html>
