<?php
require_once "includes/general/verifications.php";
if (empty($_SESSION['user_id'])) {
    app_redirect('connexion.php');
}
$pageTitle = "Warriors Training Club - Classement";
$csrfToken = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$rankingJsVersion = is_file(__DIR__ . '/js/classement.js') ? filemtime(__DIR__ . '/js/classement.js') : time();
$bytesFromIni = static function (?string $size): int {
    $size = trim((string) $size);
    if ($size === '' || $size === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($size, -1));
    $value = (float) $size;
    $factor = match ($unit) {
        'g' => 1024 ** 3,
        'm' => 1024 ** 2,
        'k' => 1024,
        default => 1,
    };
    return (int) min($value * $factor, PHP_INT_MAX);
};
$maxPhotoBytes = min(5 * 1024 * 1024, $bytesFromIni(ini_get('upload_max_filesize')));
$postMaxBytes = $bytesFromIni(ini_get('post_max_size'));
$maxPhotoTotalBytes = min(15 * 1024 * 1024, $postMaxBytes === PHP_INT_MAX ? 15 * 1024 * 1024 : max(0, $postMaxBytes - 512 * 1024));
$maxPhotoBytes = min($maxPhotoBytes, $maxPhotoTotalBytes);
$formatMegabytes = static function (int $bytes): string {
    return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1, ',', ''), '0'), ',');
};
$maxPhotoLabel = $formatMegabytes($maxPhotoBytes);
$maxPhotoTotalLabel = $formatMegabytes($maxPhotoTotalBytes);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="stylesheet" href="css/classement.css?v=8">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" sizes="any" href="./img/wtc.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./img/wtc.png">
    <meta name="application-name" content="Warriors Training Club">
    <meta name="theme-color" content="#C9A227">
</head>

<body>
    <?php require 'includes/general/navbar.php'; ?>

    <main class="ranking-app" id="rankingApp" data-api="api/classement.php" data-csrf="<?= $csrfToken ?>">
        <section class="ranking-home">
            <header class="ranking-heading">
                <div class="container">
                    <p class="eyebrow">Warriors Training Club</p>
                    <div class="ranking-heading__row">
                        <div>
                            <h1>Classement</h1>
                        </div>
                        <button class="btn btn-wtc-gold ranking-create-category" id="openCreateCategory" type="button" hidden>
                            <i class="bi bi-plus-lg" aria-hidden="true"></i><span>Créer un classement</span>
                        </button>
                    </div>
                </div>
            </header>

            <section class="section ranking-section">
                <div class="container">
                    <div class="ranking-block" id="personalBlock" hidden>
                        <div class="ranking-block__heading">
                            <div>
                                <p class="eyebrow">Tes références</p>
                                <h2>Meilleurs classements</h2>
                            </div>
                        </div>
                        <div class="ranking-personal-grid" id="personalBestList"></div>
                    </div>

                    <div class="ranking-block">
                        <div class="ranking-block__heading">
                            <div>
                                <p class="eyebrow">Toutes les épreuves</p>
                                <h2>Catégories</h2>
                            </div>
                        </div>
                        <div class="ranking-category-grid" id="categoryList" aria-live="polite">
                            <p class="ranking-message">Chargement des classements…</p>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        <section class="ranking-drawer" id="categoryDrawer" aria-hidden="true" aria-labelledby="categoryTitle" inert>
            <div class="ranking-drawer__topline">
                <button class="ranking-back" id="backToCategories" type="button" aria-label="Retour aux catégories">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Classements</span>
                </button>
                <span class="ranking-drawer__club">Warriors Training Club</span>
            </div>
            <div class="ranking-drawer__content" id="categoryContent">
                <div class="ranking-drawer__header">
                    <p class="eyebrow">Tableau des performances</p>
                    <h1 id="categoryTitle">Classement</h1>
                </div>
                <div class="ranking-podium" id="podium" aria-label="Podium des trois meilleures performances"></div>
                <div class="ranking-table-wrap">
                    <div class="ranking-table-heading">
                        <h2 id="recordListTitle">Toutes les performances</h2>
                    </div>
                    <ol class="ranking-record-list" id="recordList" aria-live="polite"></ol>
                </div>
                <section class="ranking-subcategory-section" id="subcategorySection" hidden>
                    <div class="ranking-subcategory-heading">
                        <div>
                            <h2>Sous-catégories</h2>
                        </div>
                    </div>
                    <div class="ranking-subcategory-grid" id="subcategoryList"></div>
                </section>
            </div>
        </section>
    </main>

    <div class="ranking-fixed-actions" id="categoryActions" hidden>
        <button class="btn btn-wtc-gold ranking-add-button" id="openAddRecord" type="button">
            <i class="bi bi-stopwatch" aria-hidden="true"></i><span>Ajouter un temps</span>
        </button>
    </div>

    <div class="modal fade wtc-modal" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content wtc-modal__content" id="addRecordForm" enctype="multipart/form-data">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Nouvelle performance</p><h2 class="modal-title" id="addRecordTitle">Ajouter un temps</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" data-photo-max-bytes="<?= $maxPhotoBytes ?>" data-photo-total-max-bytes="<?= $maxPhotoTotalBytes ?>">
                    <label class="ranking-label" for="competitionName">Compétition</label>
                    <input class="form-control ranking-input" id="competitionName" name="competition" maxlength="120" required placeholder="Ex. Open régional">

                    <label class="ranking-label mt-3" for="eventDate">Date de la performance</label>
                    <input class="form-control ranking-input" id="eventDate" name="event_date" type="date" value="<?= date('Y-m-d') ?>" required>

                    <div class="ranking-partner-picker">
                        <label class="ranking-label mt-3" for="partnerSearch">Participants avec toi <span class="ranking-optional">facultatif</span></label>
                        <input class="form-control ranking-input" id="partnerSearch" type="search" maxlength="160" autocomplete="off" placeholder="Rechercher ou saisir Prénom Nom">
                        <div class="ranking-partner-results" id="partnerResults" role="listbox" aria-multiselectable="true" hidden></div>
                        <div class="ranking-selected-partners" id="selectedPartners" aria-live="polite" hidden></div>
                    </div>

                    <fieldset class="ranking-time-fieldset">
                        <legend class="ranking-label">Temps réalisé</legend>
                        <label class="ranking-duration-control" for="performanceTime"><span>HH : MM : SS</span><input class="form-control ranking-input ranking-duration-input" id="performanceTime" name="performance_time" type="text" inputmode="numeric" maxlength="8" autocomplete="off" placeholder="00:00:00" aria-describedby="performanceTimeHint" required></label>
                        <small class="ranking-duration-hint" id="performanceTimeHint">Saisis les chiffres à la suite : heures, minutes, secondes.</small>
                    </fieldset>

                    <div class="ranking-photo-toolbar">
                        <span class="ranking-label mb-0">Photos <span class="ranking-optional">facultatif</span></span>
                        <span class="ranking-photo-count" id="achievementPhotoCount">0 / 10</span>
                    </div>
                    <input class="ranking-photo-input" id="achievementPhotos" type="file" accept="image/jpeg,image/png,image/webp" multiple aria-label="Choisir des photos de la performance">
                    <button class="ranking-photo-picker" id="selectAchievementPhotos" type="button">
                        <i class="bi bi-images" aria-hidden="true"></i><span>Ajouter des photos</span>
                    </button>
                    <div class="ranking-photo-list" id="achievementPhotoList" aria-live="polite">
                        <p class="ranking-photo-empty">Aucune photo sélectionnée</p>
                    </div>
                    <div class="ranking-photo-hint">JPEG, PNG ou WebP · <?= $maxPhotoLabel ?> Mo maximum par photo, <?= $maxPhotoTotalLabel ?> Mo au total</div>
                    <div class="ranking-form-error" id="addRecordError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-wtc-gold" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Continuer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="confirmRecordModal" tabindex="-1" aria-labelledby="confirmRecordTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Confirmation</p><h2 class="modal-title" id="confirmRecordTitle">Déclaration sur l’honneur</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="ranking-confirm-copy">Je confirme que les informations et les photos transmises sont exactes. Toute fausse déclaration peut entraîner une exclusion du système de classement pendant plusieurs mois.</p>
                    <div class="ranking-form-error" id="confirmRecordError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Retour</button>
                    <button class="btn btn-wtc-gold" id="confirmRecordSubmit" type="button">Je confirme</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content wtc-modal__content" id="createCategoryForm">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Nouveau classement</p><h2 class="modal-title" id="createCategoryTitle">Créer un classement</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="ranking-label" for="categoryName">Nom du classement</label>
                    <input class="form-control ranking-input" id="categoryName" name="name" maxlength="80" required placeholder="Ex. Tractions strictes">
                    <label class="ranking-subcategory-toggle" for="categoryHasSubcategories">
                        <input class="ranking-checkbox" id="categoryHasSubcategories" type="checkbox">
                        <span class="ranking-checkbox__box" aria-hidden="true"><i class="bi bi-check2"></i></span>
                        <span class="ranking-subcategory-toggle__copy">
                            <strong>Ajouter des sous-catégories</strong>
                            <small>Configurer les épreuves juste après.</small>
                        </span>
                    </label>
                    <div class="ranking-form-error" id="createCategoryError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-wtc-gold" id="createCategoryContinue" type="submit">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="subcategoryWizardModal" tabindex="-1" aria-labelledby="subcategoryWizardTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Nouvelle catégorie</p><h2 class="modal-title" id="subcategoryWizardTitle">Ajouter les sous-catégories</h2></div>
                    <button class="btn-close btn-close-white" id="closeSubcategoryWizard" type="button" aria-label="Annuler la création"></button>
                </div>
                <div class="modal-body">
                    <p class="ranking-wizard-category" id="subcategoryWizardCategory"></p>
                    <label class="ranking-label" for="subcategoryDraftName">Nom d’une sous-catégorie</label>
                    <div class="ranking-subcategory-entry">
                        <input class="form-control ranking-input" id="subcategoryDraftName" maxlength="80" placeholder="Ex. Sprint 400 m">
                        <button class="btn btn-wtc-outline" id="addSubcategoryDraft" type="button" aria-label="Ajouter cette sous-catégorie" title="Ajouter cette sous-catégorie">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <ul class="ranking-subcategory-drafts" id="subcategoryDraftList" aria-live="polite"></ul>
                    <p class="ranking-subcategory-empty" id="subcategoryDraftEmpty">Ajoute au moins une sous-catégorie pour terminer.</p>
                    <div class="ranking-form-error" id="subcategoryWizardError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer ranking-wizard-footer">
                    <button class="btn btn-wtc-gold" id="finishCategoryCreation" type="button" disabled>Terminer</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="manageSubcategoriesModal" tabindex="-1" aria-labelledby="manageSubcategoriesTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content wtc-modal__content" id="manageSubcategoriesForm">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Sous-catégories</p><h2 class="modal-title" id="manageSubcategoriesTitle">Gérer les sous-catégories</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="ranking-wizard-category" id="manageSubcategoriesCategory"></p>
                    <label class="ranking-label" for="managedSubcategoryName">Nouvelle sous-catégorie</label>
                    <div class="ranking-subcategory-entry">
                        <input class="form-control ranking-input" id="managedSubcategoryName" maxlength="80" placeholder="Ex. Sprint 400 m">
                        <button class="btn btn-wtc-outline" type="submit" aria-label="Ajouter cette sous-catégorie" title="Ajouter cette sous-catégorie"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                    </div>
                    <ul class="ranking-subcategory-drafts" id="managedSubcategoryList" aria-live="polite"></ul>
                    <div class="ranking-form-error" id="manageSubcategoriesError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Fermer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="recordDetailsModal" tabindex="-1" aria-labelledby="recordDetailsTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Détail de la performance</p><h2 class="modal-title" id="recordDetailsTitle">Résultat</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" id="recordDetailsBody"></div>
                <div class="modal-footer wtc-modal__footer" id="recordDetailsFooter">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="editRecordTimeModal" tabindex="-1" aria-labelledby="editRecordTimeTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content wtc-modal__content" id="editRecordTimeForm">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Ma performance</p><h2 class="modal-title" id="editRecordTimeTitle">Modifier ma performance</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="ranking-label" for="editPartnerSearch">Ajouter des participants <span class="ranking-optional">facultatif</span></label>
                    <input class="form-control ranking-input" id="editPartnerSearch" type="search" maxlength="160" autocomplete="off" placeholder="Rechercher ou saisir Prénom Nom">
                    <div class="ranking-partner-results" id="editPartnerResults" role="listbox" aria-multiselectable="true" hidden></div>
                    <div class="ranking-selected-partners" id="editSelectedPartners" aria-live="polite" hidden></div>
                    <label class="ranking-duration-control" for="editPerformanceTime"><span>Temps réalisé · HH : MM : SS</span><input class="form-control ranking-input ranking-duration-input" id="editPerformanceTime" name="performance_time" type="text" inputmode="numeric" maxlength="8" autocomplete="off" placeholder="00:00:00" aria-describedby="editPerformanceTimeHint" required></label>
                    <small class="ranking-duration-hint" id="editPerformanceTimeHint">Saisis les chiffres à la suite : heures, minutes, secondes.</small>
                    <p class="ranking-confirm-copy" id="editSharedTimeNotice" hidden>Cette performance est partagée : le nouveau temps sera visible pour tous ses participants.</p>
                    <div class="ranking-form-error" id="editRecordTimeError" role="alert" hidden></div>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-wtc-gold" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="rankingConfirmModal" tabindex="-1" aria-labelledby="rankingConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <div><p class="eyebrow mb-1">Action irréversible</p><h2 class="modal-title" id="rankingConfirmTitle">Confirmer</h2></div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body"><p id="rankingConfirmMessage" class="mb-0"></p></div>
                <div class="modal-footer wtc-modal__footer">
                    <button class="btn btn-wtc-outline" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-danger" id="rankingConfirmAction" type="button">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ranking-toast" id="rankingToast" role="status" aria-live="polite"></div>

    <?php require 'includes/general/footer.php'; ?>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/classement.js?v=<?= $rankingJsVersion ?>"></script>
</body>

</html>