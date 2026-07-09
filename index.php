<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";

include __DIR__ . "/includes/general/index-liens.php";
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warriors Training Club - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607051">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" sizes="any" href="./img/wtc.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./img/wtc.png">
    <meta name="application-name" content="Warriors Training Club">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Warriors">
    <meta name="msapplication-TileColor" content="#0C0B0A">
    <meta name="msapplication-TileImage" content="./img/wtc.png">
    <meta name="theme-color" content="#C9A227">
    <meta name="mobile-web-app-capable" content="yes">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Saison 2026 — 2027</span>
                    <h1 class="mt-3 mb-3">Entre dans le <span class="accent">Warriors Training Club</span></h1>
                    <p class="lead">
                        Bienvenue sur l'application du WTC. Cardio Training, Hyrox, Running-Trail et
                        préparation physique : retrouve ici les horaires de la saison, tes documents
                        d'adhésion et toutes les infos pratiques du club.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                        <a href="<?= $heroInscriptionLink ?>" class="btn btn-wtc-gold rounded-pill" target="_blank"
                            rel="noopener">
                            Inscription en ligne
                        </a>
                        <a href="seances.php" class="btn btn-wtc-outline rounded-pill">Voir les séances</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="horaires">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Programme hebdomadaire</p>
                <h2>Horaires saison 2026-2027</h2>
            </div>

            <div class="season-card">
                <span class="season-pill">Saison 2026-2027</span>
                <p class="season-card__title">Cardio Training / Hyrox / Running-Trail / Préparation physique</p>
                <p class="season-card__sub">Ados à partir de 15 ans &amp; adultes tous niveaux — débutants à confirmés.
                </p>

                <div class="timetable">
                    <div class="timetable-row">
                        <span class="timetable-row__day">Mardi</span>
                        <span class="timetable-row__time">20h15 — 21h30</span>
                        <span class="timetable-row__place">Dojo</span>
                    </div>
                    <div class="timetable-row">
                        <span class="timetable-row__day">Jeudi</span>
                        <span class="timetable-row__time">19h30 — 21h00</span>
                        <span class="timetable-row__place">Stade</span>
                    </div>
                    <div class="timetable-row">
                        <span class="timetable-row__day">Samedi</span>
                        <span class="timetable-row__time">10h00 — 11h30</span>
                        <span class="timetable-row__place">Dojo</span>
                    </div>
                </div>

                <p class="season-note">
                    Retrouve le détail des contenus sur l'onglet <a href="seances.php">Séances</a> pour visualiser les
                    séances de la semaine.
                </p>
            </div>
        </div>
    </section>

    <section class="section" id="liens">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Accès rapide</p>
                <h2>Inscriptions &amp; boutiques</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $heroInscriptionLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Adhésion</p>
                            <p class="link-card__title">Inscription en ligne</p>
                        </div>
                        <span class="link-card__arrow">HelloAsso <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $cardBoutiqueBarresLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Boutique</p>
                            <p class="link-card__title">Barres de céréales Les Craq's</p>
                        </div>
                        <span class="link-card__arrow">HelloAsso <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $cardBoutiqueVetementsLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Boutique</p>
                            <p class="link-card__title">Vêtements Warriors</p>
                        </div>
                        <span class="link-card__arrow">Market Factory <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="sante">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Dossier d'adhésion</p>
                <h2>Questionnaires de santé</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-md-6">
                    <div class="dossier">
                        <div class="dossier__head">
                            <h3>Je suis majeur</h3>
                            <span class="dossier__stamp">Majeur</span>
                        </div>
                        <div class="dossier__body">
                            <a class="doc-link" href="img/Questionnaire Santé Majeur.pdf" target="_blank">
                                <span>Questionnaire de santé</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                            <a class="doc-link" href="img/Attestation Questionnaire Santé Majeur.pdf" target="_blank">
                                <span>Attestation</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="dossier">
                        <div class="dossier__head">
                            <h3>Je suis mineur</h3>
                            <span class="dossier__stamp">Mineur</span>
                        </div>
                        <div class="dossier__body">
                            <a class="doc-link" href="img/Questionnaire Santé Mineur.pdf" target="_blank">
                                <span>Questionnaire de santé</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                            <a class="doc-link" href="img/Attestation Questionnaire Santé Mineur.pdf" target="_blank">
                                <span>Attestation</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="adresse">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Où nous trouver</p>
                <h2>Dojo Teddy Riner</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-lg-5">
                    <div class="address-card">
                        <div>
                            <h3>Complexe sportif Teddy Riner</h3>
                            <p>77720 Mormant</p>
                        </div>
                        <a class="btn btn-wtc-outline rounded-pill align-self-start" href="<?= $cardMapLink ?>"
                            target="_blank" rel="noopener">
                            <i class="bi bi-geo-alt me-1"></i>Cliquez ici si vous êtes perdu
                        </a>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="map-frame">
                        <iframe src="https://www.google.com/maps?q=48.6113915,2.8807517&z=16&output=embed"
                            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="Localisation du Dojo Teddy Riner - Warriors Training Club">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>