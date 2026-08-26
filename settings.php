<?php
require_once 'includes/general/session-config.php';
require_once 'includes/general/verifications.php';
require_once 'includes/general/db.php';
require_once 'includes/general/app-settings.php';

$currentId = $_SESSION['user_id'] ?? 0;
if ($currentId <= 0 || (int) ($_SESSION['admin'] ?? 0) !== 1) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = null;

// Read current settings (in seconds)
$currentTimeoutSeconds = (int) get_setting('session_timeout_seconds', (string)(14 * 86400));
$currentModalThresholdSeconds = (int) get_setting('session_modal_threshold_seconds', (string)(24 * 3600));

function seconds_to_unit(int $seconds)
{
    $units = [
        'years' => 31557600,
        'months' => 2592000,
        'weeks' => 604800,
        'days' => 86400,
        'hours' => 3600,
        'minutes' => 60,
        'seconds' => 1,
    ];
    foreach ($units as $k => $v) {
        if ($seconds % $v === 0) {
            return [$seconds / $v, $k];
        }
    }
    return [$seconds, 'seconds'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $valTimeout = (int) ($_POST['session_timeout_value'] ?? 0);
    $unitTimeout = $_POST['session_timeout_unit'] ?? 'days';
    $valModal = (int) ($_POST['modal_threshold_value'] ?? 0);
    $unitModal = $_POST['modal_threshold_unit'] ?? 'days';

    $multipliers = [
        'seconds' => 1,
        'minutes' => 60,
        'hours' => 3600,
        'days' => 86400,
        'weeks' => 604800,
        'months' => 2592000,
        'years' => 31557600,
    ];

    if ($valTimeout < 1 || $valTimeout > 1000000 || !isset($multipliers[$unitTimeout])) {
        $errors[] = 'Valeur de durée invalide.';
    }
    if ($valModal < 0 || $valModal > 1000000 || !isset($multipliers[$unitModal])) {
        $errors[] = 'Valeur du seuil modal invalide.';
    }

    if (empty($errors)) {
        $timeoutSeconds = $valTimeout * $multipliers[$unitTimeout];
        $modalSeconds = $valModal * $multipliers[$unitModal];
        if (set_setting('session_timeout_seconds', (string)$timeoutSeconds) && set_setting('session_modal_threshold_seconds', (string)$modalSeconds)) {
            $success = 'Les paramètres ont été enregistrés.';
            $currentTimeoutSeconds = $timeoutSeconds;
            $currentModalThresholdSeconds = $modalSeconds;
        } else {
            $errors[] = 'Impossible d’enregistrer les paramètres.';
        }
    }
}

$pageTitle = 'Warriors Training Club - Paramètres';
include __DIR__ . '/includes/general/administration.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= htmlspecialchars($assetVersion ?? '') ?>">
</head>
<body>
<?php require 'includes/general/navbar.php'; ?>
<section class="section">
    <div class="container">
        <h1 class="mb-3">Paramètres</h1>

        <?php if (!empty($errors)): ?>
            <div class="auth-alert auth-alert--error">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success">
                <p class="mb-0"><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Déconnexion automatique</label>
                    <?php [$vTimeout, $uTimeout] = seconds_to_unit($currentTimeoutSeconds); ?>
                    <div class="input-group">
                        <input type="number" name="session_timeout_value" class="form-control" min="1" value="<?= htmlspecialchars((string)$vTimeout) ?>">
                        <select name="session_timeout_unit" class="form-select">
                            <?php foreach (['seconds','minutes','hours','days','weeks','months','years'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $opt === $uTimeout ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Par défaut : 14 jours.</div>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label">Définir le temps limite d'apparition du modal d'alerte de déconnexion</label>
                    <?php [$vModal, $uModal] = seconds_to_unit($currentModalThresholdSeconds); ?>
                    <div class="input-group">
                        <input type="number" name="modal_threshold_value" class="form-control" min="0" value="<?= htmlspecialchars((string)$vModal) ?>">
                        <select name="modal_threshold_unit" class="form-select">
                            <?php foreach (['seconds','minutes','hours','days','weeks','months','years'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $opt === $uModal ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-text">Si la valeur est 0, le modal n'apparaîtra pas.</div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" name="save_settings" class="btn btn-wtc-gold">Enregistrer</button>
            </div>
        </form>
    </div>
</section>

<?php require 'includes/general/footer.php'; ?>

<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
