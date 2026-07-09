<?php
require_once "includes/general/verifications.php";
$pageTitle = "Warriors Training Club - Règlement intérieur";
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warriors Training Club - Règlement intérieur</title>
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
    <link rel="icon" type="image/png" href="./img/wtc.png">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Document officiel</span>
                    <h1 class="mt-3 mb-3">Règlement <span class="accent">intérieur</span></h1>
                    <p class="lead">
                        Applicable à tous les adhérents du Warriors Training Club et de ses sections.
                        L'adhésion au club implique l'acceptation pleine et entière de ce règlement.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="reglement">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">À lire avant adhésion</p>
                <h2>Articles du règlement</h2>
            </div>

            <div class="reglement-card">

                <div class="reglement-item">
                    <span class="reglement-item__num">01</span>
                    <p class="reglement-item__text">
                        L'inscription et la cotisation sont obligatoires pour la pratique des activités dispensées
                        au sein du Warriors Training Club et de ses sections. Les prix et les règles particulières
                        à chaque activité sont fixés par l'assemblée générale.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">02</span>
                    <p class="reglement-item__text">
                        Le certificat médical de non contre-indication à la pratique des activités choisies ou le
                        questionnaire santé (si les réponses n'imposent pas un avis médical) est obligatoire pour
                        tous les adhérents.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">03</span>
                    <p class="reglement-item__text">
                        Les activités enfants sont encadrées par un enseignant diplômé ou des bénévoles en formation.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">04</span>
                    <p class="reglement-item__text">
                        Les activités spécifiquement adultes (cardio training / running) ne sont pas encadrées et sont
                        de libre accès. L'accès éventuel d'ados de plus de 15 ans à ces activités est subordonné à la
                        présence obligatoire d'un représentant légal, lui-même adhérent.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">05</span>
                    <p class="reglement-item__text">
                        Deux séances d'essai maximum pourront être effectuées avant l'adhésion.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">06</span>
                    <p class="reglement-item__text">
                        Pour une question d'assurance, tous les documents (fiche d'inscription, certificat médical ou
                        questionnaire, cotisation) doivent être fournis à la fin de cette période d'essai et au plus
                        tard au 1er octobre. Une assurance (RC et DC) est incluse dans l'adhésion afin de couvrir les
                        activités en commun avec d'autres associations partenaires.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">07</span>
                    <p class="reglement-item__text">
                        Le paiement de la cotisation pourra être réglé en 2 fois maximum et remis au plus tard à la fin
                        de la période d'essai. L'encaissement du premier règlement (d'un minimum de 85€) se fera fin
                        septembre et le dernier règlement se fera au plus tard le 31 décembre (le bureau se réserve le
                        droit d'étudier tout cas particulier).
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">08</span>
                    <p class="reglement-item__text">
                        Aucun remboursement ne pourra être effectué.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">09</span>
                    <p class="reglement-item__text">
                        Les adhérents et leur famille doivent respecter les règles d'accès et d'utilisation relatives
                        aux installations municipales définies par la mairie de Mormant et les règles sanitaires en
                        vigueur. Les tenues (judogi et/ou tenue de sport) et les adhérents doivent respecter les règles
                        d'hygiène et de sécurité.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">10</span>
                    <p class="reglement-item__text">
                        Les enfants mineurs sont pris en charge par le club à partir du moment où le responsable du
                        créneau horaire les aura pris sous sa responsabilité à l'intérieur du dojo Teddy Riner et autres
                        lieux d'activités. Les parents doivent donc s'assurer que celui-ci est bien présent avant de
                        laisser leurs enfants. De plus, lorsque la fin du créneau horaire est atteinte, les enfants ne
                        sont plus considérés comme étant sous la responsabilité du club, qu'ils soient ou non encore
                        dans l'enceinte du dojo ou tous autres lieux d'activités. La sortie du dojo et de tous autres
                        lieux d'activités se fait donc sous la seule responsabilité des parents quelles que soient les
                        modalités choisies par eux (seul, accompagné par un tiers, …).
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">11</span>
                    <p class="reglement-item__text">
                        Les parents et autres accompagnants (autres enfants, …) ne sont pas admis aux abords du tatami
                        pendant les horaires de cours (judo) et des activités libres (cardio training). Toutefois
                        ceux-ci peuvent être occasionnellement tolérés (après validation par le responsable du créneau
                        horaire) à condition que leur présence ne nuise pas au bon déroulement des entraînements (le
                        silence est obligatoire), à la sécurité (capacité d'accueil du dojo) et aux règles sanitaires
                        en vigueur.
                    </p>
                </div>

                <div class="reglement-item reglement-item--final">
                    <span class="reglement-item__num">12</span>
                    <p class="reglement-item__text">
                        Le règlement intérieur a la même force obligatoire pour tous les adhérents du club. Nul ne
                        pourra s'y soustraire puisqu'implicitement accepté lors de l'adhésion. L'adhérent et sa famille
                        s'engagent à respecter le règlement intérieur ainsi que les conditions d'adhésion sous peine
                        d'exclusion.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <section class="section" id="reglement-app">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Utilisation du site et de l'application</p>
                <h2>Règlement de l'application</h2>
            </div>

            <div class="reglement-card">

                <div class="reglement-item">
                    <span class="reglement-item__num">01</span>
                    <p class="reglement-item__text">
                        L'utilisateur doit avoir l'âge légal requis ou disposer de l'autorisation d'un représentant
                        légal pour créer un compte et utiliser l'application. Les informations fournies lors de
                        l'inscription doivent être exactes, complètes et tenues à jour.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">02</span>
                    <p class="reglement-item__text">
                        L'utilisateur est responsable de la confidentialité de ses identifiants de connexion
                        (identifiant, mot de passe) et de toute activité effectuée depuis son compte. Toute
                        utilisation frauduleuse ou suspectée doit être signalée sans délai.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">03</span>
                    <p class="reglement-item__text">
                        Il est interdit d'utiliser l'application à des fins illégales, frauduleuses ou contraires aux
                        bonnes mœurs, ainsi que de tenter d'accéder sans autorisation à des données, comptes ou
                        systèmes ne vous appartenant pas.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">04</span>
                    <p class="reglement-item__text">
                        Toute tentative de perturbation du bon fonctionnement de l'application (introduction de virus,
                        attaque informatique, extraction massive de données, contournement des mesures de sécurité,
                        etc.) est strictement interdite et pourra faire l'objet de poursuites.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">05</span>
                    <p class="reglement-item__text">
                        Les contenus publiés ou transmis via l'application (messages, commentaires, documents, images)
                        doivent respecter la loi, les droits d'autrui et ne doivent contenir aucun propos injurieux,
                        diffamatoire, discriminatoire, violent ou portant atteinte à la vie privée d'un tiers.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">06</span>
                    <p class="reglement-item__text">
                        Les données personnelles collectées sont traitées conformément à la réglementation en vigueur
                        (RGPD). L'utilisateur dispose d'un droit d'accès, de rectification, de suppression et
                        d'opposition au traitement de ses données, qu'il peut exercer auprès du responsable du
                        traitement.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">07</span>
                    <p class="reglement-item__text">
                        L'éditeur de l'application met en œuvre les moyens raisonnables pour assurer la disponibilité,
                        la sécurité et le bon fonctionnement du service, sans toutefois garantir une disponibilité
                        continue ni l'absence totale d'erreurs ou d'interruptions (maintenance, cas de force majeure,
                        panne technique, etc.).
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">08</span>
                    <p class="reglement-item__text">
                        L'éditeur se réserve le droit de suspendre ou de supprimer, sans préavis, tout compte ne
                        respectant pas le présent règlement, la loi ou portant atteinte au bon fonctionnement de
                        l'application ou aux droits d'autres utilisateurs.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">09</span>
                    <p class="reglement-item__text">
                        Les marques, logos, textes, images et autres éléments présents sur l'application sont protégés
                        par le droit de la propriété intellectuelle. Toute reproduction ou utilisation non autorisée
                        est interdite.
                    </p>
                </div>

                <div class="reglement-item">
                    <span class="reglement-item__num">10</span>
                    <p class="reglement-item__text">
                        Le présent règlement peut être modifié à tout moment afin de l'adapter aux évolutions légales,
                        techniques ou fonctionnelles de l'application. Les utilisateurs seront informés de toute
                        modification substantielle et pourront être invités à en accepter à nouveau les termes.
                    </p>
                </div>

                <div class="reglement-item reglement-item--final">
                    <span class="reglement-item__num">11</span>
                    <p class="reglement-item__text">
                        En cas de désaccord persistant avec ces conditions, l'utilisateur est invité à cesser toute
                        utilisation de l'application et à en informer les administrateurs afin que son compte soit
                        désactivé.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>