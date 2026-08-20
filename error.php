<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erreur — Warriors Training Club</title>
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
                <h1 class="error-title">Oups, il semblerait que cette page ne fonctionne pas...</h1>
                <p class="error-desc">Nous sommes désolés — une erreur est survenue. Essaie de rafraîchir la page ou reviens plus tard.</p>
                <p>
                    <a class="btn btn-wtc-gold rounded-pill" href="/">Retour à l'accueil</a>
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
