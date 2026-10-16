<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$pageTitle = "Warriors Training Club - Séances";
$canManage = (int) ($_SESSION['gerer_seances'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0, private">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202610161115">
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

    <section class="section seances-section">
        <div class="container">

            <div class="section-head seances-section__head d-flex justify-content-between align-items-center gap-3">
                <div>
                    <p class="eyebrow">Programme du club</p>
                    <h2>Séances</h2>
                </div>
                <?php if ($canManage): ?>
                    <div class="seances-section__actions d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-wtc-gold rounded-pill" id="btnAjouterSeance"
                            aria-label="Créer une séance" title="Créer une séance">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-wtc-outline rounded-pill" id="btnTemplateManager"
                            data-bs-toggle="modal" data-bs-target="#templateManagerModal"
                            aria-label="Programmes de séances" title="Programmes de séances">
                            <i class="bi bi-calendar-week" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="calendar-card">
                <div class="calendar-header">
                    <button type="button" class="calendar-nav" id="calPrev" aria-label="Mois précédent">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <p class="calendar-title" id="calTitle">—</p>
                    <button type="button" class="calendar-nav" id="calNext" aria-label="Mois suivant">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <div class="calendar-weekdays">
                    <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
                </div>

                <div class="calendar-grid" id="calGrid"></div>
            </div>

            <div class="mt-5">
                <div class="section-head">
                    <p class="eyebrow">À venir</p>
                    <h2>Prochaines séances</h2>
                </div>

                <div id="upcomingList" class="upcoming-list">
                    <p class="upcoming-empty" id="upcomingEmpty" style="display:none;">
                        Aucune séance à venir pour le moment.
                    </p>
                </div>
            </div>

        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <div class="modal fade wtc-modal" id="dayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title" id="dayModalTitle">Séances du jour</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body" id="dayModalBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="seanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title">Détail de la séance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="seance-detail" id="seanceDetailBody"></div>
                </div>
                <div class="modal-footer wtc-modal__footer" id="seanceModalActions"></div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="exercicesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title" id="exercicesModalTitle">Exercices de la séance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div id="exercicesAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                    <p class="auth-hint">Notez les exercices et les consignes à suivre pendant la séance.</p>
                    <textarea class="form-control auth-input" id="exercicesNote" rows="22"
                        placeholder="Ex. : échauffement, exercices, séries et répétitions…"></textarea>
                </div>
                <div class="modal-footer wtc-modal__footer">
                    <button type="button" class="btn btn-wtc-gold rounded-pill" id="btnEnregistrerExercices" style="display:none;">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="inscritsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title">Inscrits</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body" id="inscritsBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="choixInscriptionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title">S'inscrire</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body d-flex flex-column gap-3">
                    <button type="button" class="btn btn-wtc-gold rounded-pill" id="btnMInscrire">M'inscrire</button>
                    <button type="button" class="btn btn-wtc-outline rounded-pill"
                        id="btnInscrireQuelquunFromChoix">Inscrire quelqu'un</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="inscrireQuelquunModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title">Inscrire quelqu'un</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div id="inscrireQuelquunAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                    <form id="formInscrireQuelquun" novalidate data-analytics-label="inscription_invite_seance">
                        <div class="mb-3">
                            <label for="guestFirstname" class="form-label">Prénom</label>
                            <input type="text" class="form-control auth-input" id="guestFirstname" required
                                maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="guestLastname" class="form-label">Nom</label>
                            <input type="text" class="form-control auth-input" id="guestLastname" required
                                maxlength="150">
                        </div>
                        <button type="submit" class="btn btn-wtc-gold rounded-pill w-100">Inscrire</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade wtc-modal" id="editSeanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title">Modifier la séance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div id="editSeanceAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                    <form id="formEditSeance" novalidate data-analytics-label="modification_seance">
                        <input type="hidden" id="editSeanceId">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="editSeanceDate" class="form-label">Date</label>
                                <input type="date" class="form-control auth-input" id="editSeanceDate" required>
                            </div>
                            <div class="col-6">
                                <label for="editSeanceHeureDebut" class="form-label">Début</label>
                                <input type="time" class="form-control auth-input" id="editSeanceHeureDebut" required>
                            </div>
                            <div class="col-6">
                                <label for="editSeanceHeureFin" class="form-label">Fin</label>
                                <input type="time" class="form-control auth-input" id="editSeanceHeureFin" required>
                            </div>
                            <div class="col-12">
                                <label for="editSeanceType" class="form-label">Type de séance</label>
                                <input type="text" class="form-control auth-input" id="editSeanceType" required
                                    maxlength="100">
                            </div>
                            <div class="col-12">
                                <label for="editSeanceCoach" class="form-label">Coach</label>
                                <input type="text" class="form-control auth-input" id="editSeanceCoach" maxlength="150">
                            </div>
                            <div class="col-12">
                                <label for="editSeanceLieu" class="form-label">Lieu</label>
                                <input type="text" class="form-control auth-input" id="editSeanceLieu" maxlength="150">
                            </div>
                            <div class="col-12">
                                <label for="editSeanceRdv" class="form-label">Lieu de rendez-vous</label>
                                <input type="text" class="form-control auth-input" id="editSeanceRdv" maxlength="150">
                            </div>
                            <div class="col-12">
                                <label for="editSeanceDescription" class="form-label">Descriptif</label>
                                <textarea class="form-control auth-input" id="editSeanceDescription" rows="3"
                                    maxlength="2000"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-wtc-gold rounded-pill w-100 mt-4">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <div class="modal fade wtc-modal" id="templateManagerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content wtc-modal__content">
                    <div class="modal-header wtc-modal__header">
                        <h5 class="modal-title">Programmes de séances</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <p class="mb-1 fw-semibold">Des séances régulières, prêtes à ajouter au calendrier</p>
                            </div>
                            <button type="button" class="btn btn-wtc-outline rounded-pill btn-sm" id="btnNewTemplate">
                                Créer un programme
                            </button>
                        </div>
                        <div id="templateManagerList" class="d-flex flex-column gap-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade wtc-modal" id="newTemplateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content wtc-modal__content">
                    <div class="modal-header wtc-modal__header">
                        <h5 class="modal-title">Créer un programme</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div id="templateFormAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                        <form id="formCreateTemplate" novalidate data-analytics-label="creation_modele_seances">
                            <div class="template-wizard-step" data-template-step="1">
                                <p class="fw-semibold">Donnez un nom à votre programme</p>
                                <div class="mb-3">
                                    <label for="templateName" class="form-label">Nom du programme</label>
                                    <input type="text" class="form-control auth-input" id="templateName" required
                                        maxlength="100">
                                </div>
                            </div>
                            <div class="template-wizard-step" data-template-step="2" hidden>
                                <div class="mb-3">
                                    <p class="fw-semibold mb-1">Ajoutez les séances régulières</p>
                                    <p class="auth-hint mb-0">Choisissez les jours et horaires qui se répètent chaque semaine.</p>
                                </div>
                                <div id="templateSessionsList" class="d-flex flex-column gap-2"></div>
                                <button type="button" class="btn btn-wtc-outline rounded-pill w-100 mt-3"
                                    id="btnAddTemplateSession">Ajouter une séance type</button>
                            </div>
                            <div class="template-wizard-step" data-template-step="3" hidden>
                                <p class="fw-semibold">Vérifiez votre programme</p>
                                <p class="auth-hint">Vous pourrez encore revenir en arrière pour le modifier.</p>
                                <div id="templateReviewAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                                <div id="templateRecap" class="wizard-summary"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer wtc-modal__footer">
                        <span class="wizard-step-label" id="templateStepLabel">Étape 1 sur 2</span>
                        <button type="button" class="btn btn-wtc-outline rounded-pill" id="templateWizardBack" hidden>
                            Retour
                        </button>
                        <button type="button" class="btn btn-wtc-gold rounded-pill" id="templateWizardNext">
                            Continuer
                        </button>
                        <button type="submit" form="formCreateTemplate" class="btn btn-wtc-gold rounded-pill"
                            id="templateWizardSave" hidden>
                            Confirmer et enregistrer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade wtc-modal" id="templateSessionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content wtc-modal__content">
                    <div class="modal-header wtc-modal__header">
                        <h5 class="modal-title">Ajouter une séance au programme</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div id="templateSessionAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                        <form id="formTemplateSession" novalidate data-analytics-label="ajout_seance_modele">
                            <div class="template-session-wizard-step" data-template-session-step="1">
                                <p class="fw-semibold">Quand cette séance a-t-elle lieu ?</p>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="templateSessionWeekday" class="form-label">Jour de la semaine</label>
                                        <select class="form-select auth-input" id="templateSessionWeekday" required>
                                            <option value="">Sélectionner</option>
                                            <option value="1">Lundi</option>
                                            <option value="2">Mardi</option>
                                            <option value="3">Mercredi</option>
                                            <option value="4">Jeudi</option>
                                            <option value="5">Vendredi</option>
                                            <option value="6">Samedi</option>
                                            <option value="7">Dimanche</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label for="templateSessionHeureDebut" class="form-label">Début</label>
                                        <input type="time" class="form-control auth-input" id="templateSessionHeureDebut"
                                            required>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label for="templateSessionHeureFin" class="form-label">Fin</label>
                                        <input type="time" class="form-control auth-input" id="templateSessionHeureFin"
                                            required>
                                    </div>
                                </div>
                            </div>
                            <div class="template-session-wizard-step" data-template-session-step="2" hidden>
                                <p class="fw-semibold">Quel type de séance ?</p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Type de séance</label>
                                        <div class="wtc-choice-list" role="listbox" aria-label="Type de séance">
                                            <button type="button" class="wtc-choice-pill" data-type-group="template"
                                                data-value="Cardio Training">Cardio Training</button>
                                            <button type="button" class="wtc-choice-pill" data-type-group="template"
                                                data-value="Hyrox">Hyrox</button>
                                            <button type="button" class="wtc-choice-pill" data-type-group="template"
                                                data-value="Running-Trail">Running-Trail</button>
                                            <button type="button" class="wtc-choice-pill" data-type-group="template"
                                                data-value="Préparation physique">Préparation physique</button>
                                            <button type="button" class="wtc-choice-pill" data-type-group="template"
                                                data-value="__autre__">Autre…</button>
                                        </div>
                                        <input type="hidden" id="templateSessionType" name="templateSessionType" value="">
                                        <input type="text" class="form-control auth-input mt-2" id="templateSessionTypeAutre"
                                            placeholder="Préciser le type de séance" style="display:none;">
                                    </div>
                                </div>
                            </div>
                            <div class="template-session-wizard-step" data-template-session-step="3" hidden>
                                <p class="fw-semibold">Complétez les détails (facultatif)</p>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="templateSessionCoach" class="form-label">Coach</label>
                                        <input type="text" class="form-control auth-input" id="templateSessionCoach"
                                            maxlength="150">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label for="templateSessionLieu" class="form-label">Lieu de la séance</label>
                                        <input type="text" class="form-control auth-input" id="templateSessionLieu"
                                            maxlength="150" placeholder="Ex : Dojo, Stade…">
                                    </div>
                                    <div class="col-12">
                                        <label for="templateSessionRdv" class="form-label">Lieu de rendez-vous</label>
                                        <input type="text" class="form-control auth-input" id="templateSessionRdv"
                                            maxlength="150">
                                    </div>
                                    <div class="col-12">
                                        <label for="templateSessionDescription" class="form-label">Descriptif <span
                                                class="auth-optional">(facultatif)</span></label>
                                        <textarea class="form-control auth-input" id="templateSessionDescription" rows="3"
                                            maxlength="2000"></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer wtc-modal__footer">
                        <span class="wizard-step-label" id="templateSessionStepLabel">Étape 1 sur 3</span>
                        <button type="button" class="btn btn-wtc-outline rounded-pill" id="templateSessionBack" hidden>
                            Retour
                        </button>
                        <button type="button" class="btn btn-wtc-gold rounded-pill" id="templateSessionNext">
                            Continuer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade wtc-modal" id="ajouterSeanceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content wtc-modal__content">
                    <div class="modal-header wtc-modal__header">
                        <h5 class="modal-title">Ajouter une séance</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="auth-hint">Cette séance apparaîtra dans le calendrier du club.</p>
                        <div id="ajouterSeanceStepAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                        <div id="ajouterSeanceAlert" class="auth-alert auth-alert--error" style="display:none;"></div>
                        <form id="formAjouterSeance" novalidate data-analytics-label="creation_seance">
                            <div class="seance-wizard-step" data-seance-step="1">
                                <p class="fw-semibold">Quand aura lieu la séance ?</p>
                                <div class="row g-2 g-sm-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="newDate" class="form-label">Date</label>
                                        <input type="date" class="form-control auth-input" id="newDate" required>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label for="newHeureDebut" class="form-label">Début</label>
                                        <input type="time" class="form-control auth-input" id="newHeureDebut" required>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <label for="newHeureFin" class="form-label">Fin</label>
                                        <input type="time" class="form-control auth-input" id="newHeureFin" required>
                                    </div>
                                </div>
                            </div>
                        <div class="seance-wizard-step" data-seance-step="2" hidden>
                            <p class="fw-semibold">Quel type de séance ?</p>
                            <div class="row g-2 g-sm-3">
                                <div class="col-12">
                                    <label class="form-label">Type de séance</label>
                                    <div class="wtc-choice-list" role="listbox" aria-label="Type de séance">
                                        <button type="button" class="wtc-choice-pill" data-type-group="new"
                                            data-value="Cardio Training">Cardio
                                            Training</button>
                                        <button type="button" class="wtc-choice-pill" data-type-group="new"
                                            data-value="Hyrox">Hyrox</button>
                                        <button type="button" class="wtc-choice-pill" data-type-group="new"
                                            data-value="Running-Trail">Running-Trail</button>
                                        <button type="button" class="wtc-choice-pill" data-type-group="new"
                                            data-value="Préparation physique">Préparation physique</button>
                                        <button type="button" class="wtc-choice-pill" data-type-group="new"
                                            data-value="__autre__">Autre…</button>
                                    </div>
                                    <input type="hidden" id="newType" name="newType" value="">
                                    <input type="text" class="form-control auth-input mt-2" id="newTypeAutre"
                                        placeholder="Préciser le type de séance" style="display:none;">
                                </div>
                            </div>
                        </div>
                        <div class="seance-wizard-step" data-seance-step="3" hidden>
                            <p class="fw-semibold">Qui encadre et où se retrouve le groupe ?</p>
                            <div class="row g-2 g-sm-3">
                                <div class="col-12 col-sm-6">
                                    <label for="newCoach" class="form-label">Coach</label>
                                    <input type="text" class="form-control auth-input" id="newCoach" required
                                        maxlength="150">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label for="newLieuSeance" class="form-label">Lieu de la séance</label>
                                    <input type="text" class="form-control auth-input" id="newLieuSeance" required
                                        maxlength="150" placeholder="Ex : Dojo, Stade…">
                                </div>
                                <div class="col-12">
                                    <label for="newLieuRdv" class="form-label">Lieu de rendez-vous</label>
                                    <input type="text" class="form-control auth-input" id="newLieuRdv" required
                                        maxlength="150">
                                </div>
                            </div>
                            </div>
                            <div class="seance-wizard-step" data-seance-step="4" hidden>
                                <p class="fw-semibold">Vérifiez les informations de la séance</p>
                                <p class="auth-hint">Vous pourrez revenir en arrière pour les modifier.</p>
                                <div id="ajouterSeanceRecap" class="wizard-summary"></div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer wtc-modal__footer ajouter-seance-modal__footer">
                        <span class="wizard-step-label" id="ajouterSeanceStepLabel">Étape 1 sur 3</span>
                        <button type="button" class="btn btn-wtc-outline rounded-pill" id="ajouterSeanceBack" hidden>
                            Retour
                        </button>
                        <button type="button" class="btn btn-wtc-gold rounded-pill" id="ajouterSeanceNext">
                            Continuer
                        </button>
                        <button type="submit" form="formAjouterSeance" class="btn btn-wtc-gold rounded-pill"
                            id="ajouterSeanceSave" hidden>
                            Confirmer et créer
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
        <div id="wtcToast" class="toast wtc-toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-body" id="wtcToastBody"></div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        window.WTC_CONTEXT = {
            canManage: <?php echo $canManage ? 'true' : 'false'; ?>,
            isAdmin: <?php echo ((int) ($_SESSION['admin'] ?? 0) === 1) ? 'true' : 'false'; ?>
        };
        window.WTC_CURRENT_USER_ID = <?php echo (int) ($_SESSION['user_id'] ?? 0); ?>;
    </script>
        <?php if (isset($_GET['open']) && is_numeric($_GET['open'])): ?>
        <script>
            window.WTC_OPEN_SEANCE = <?php echo (int) $_GET['open']; ?>;
        </script>
        <?php endif; ?>
    <script src="js/seances.js?v=202610161115"></script>

</body>

</html>
