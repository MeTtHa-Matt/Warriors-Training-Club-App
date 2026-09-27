<?php
require_once __DIR__ . '/../includes/general/session-config.php';
require_once __DIR__ . '/../includes/general/db.php';
require_once __DIR__ . '/../includes/general/classement-storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function classementRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function classementReleaseLock(PDO $pdo, bool &$lockHeld): void
{
    if (!$lockHeld) {
        return;
    }

    try {
        $pdo->query("SELECT RELEASE_LOCK('wtc_ranking_storage')");
    } catch (Throwable $error) {
        error_log('[classement] unable to release database lock: ' . $error->getMessage());
    }
    $lockHeld = false;
}

function classementParticipants(array $record): array
{
    if (isset($record['participants']) && is_array($record['participants'])) {
        return array_values(array_filter($record['participants'], static function ($participant) {
            if (!is_array($participant)) {
                return false;
            }
            if ((int) ($participant['user_id'] ?? 0) > 0) {
                return true;
            }
            return !empty($participant['external'])
                && trim((string) ($participant['firstname'] ?? '')) !== ''
                && trim((string) ($participant['lastname'] ?? '')) !== '';
        }));
    }

    $userId = (int) ($record['user_id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }
    return [[
        'user_id' => $userId,
        'firstname' => (string) ($record['firstname'] ?? 'Membre'),
        'last_initial' => (string) ($record['last_initial'] ?? ''),
    ]];
}

function classementExpandRecords(array $records): array
{
    $expanded = [];
    foreach ($records as $record) {
        $participants = classementParticipants($record);
        foreach ($participants as $participant) {
            $expanded[] = array_merge($record, [
                'user_id' => (int) $participant['user_id'],
                'participant_id' => (int) $participant['user_id'],
                'firstname' => (string) ($participant['firstname'] ?? 'Membre'),
                'lastname' => (string) ($participant['lastname'] ?? ''),
                'last_initial' => (string) ($participant['last_initial'] ?? ''),
                'external' => !empty($participant['external']),
                'owner_id' => (int) ($record['owner_id'] ?? $record['user_id'] ?? 0),
                'participants' => $participants,
            ]);
        }
    }
    return $expanded;
}

function classementRemovePhotos(array $records, string $uploadDirectory): void
{
    foreach ($records as $record) {
        foreach (($record['photos'] ?? []) as $photo) {
            $filename = basename((string) $photo);
            if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
                @unlink($uploadDirectory . '/' . $filename);
            }
        }
    }
}

function classementSavePhotos(array $files, string $uploadDirectory): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $names = $files['name'];
    $saved = [];
    $fileCount = count(array_filter($names, static fn($name) => $name !== ''));
    if ($fileCount > 10) {
        throw new InvalidArgumentException('Ajoute au maximum 10 photos.');
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Impossible de préparer le stockage des photos.');
    }

    try {
        foreach ($names as $index => $originalName) {
            if ($originalName === '') {
                continue;
            }
            $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Chaque photo doit faire 5 Mo maximum.'
                    : 'Une photo n’a pas pu être envoyée.');
            }

            $temporaryPath = $files['tmp_name'][$index] ?? '';
            if (!is_uploaded_file($temporaryPath) || ($files['size'][$index] ?? 0) > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('Une photo est invalide ou dépasse 5 Mo.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($temporaryPath);
            $imageInfo = @getimagesize($temporaryPath);
            if (!isset($allowed[$mime]) || $imageInfo === false || $imageInfo['mime'] !== $mime
                || $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
                throw new InvalidArgumentException('Format de photo invalide. Utilise JPEG, PNG ou WebP.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $filename)) {
                throw new RuntimeException('Impossible d’enregistrer une photo.');
            }
            chmod($uploadDirectory . '/' . $filename, 0644);
            $saved[] = $filename;
        }
    } catch (Throwable $error) {
        classementRemovePhotos([['photos' => $saved]], $uploadDirectory);
        throw $error;
    }

    return $saved;
}

if (empty($_SESSION['user_id'])) {
    classementRespond(['error' => 'Connecte-toi pour consulter le classement.'], 401);
}

$userId = (int) $_SESSION['user_id'];
$userQuery = $pdo->prepare('SELECT id, firstname, lastname, admin FROM account_wtc WHERE id = ?');
$userQuery->execute([$userId]);
$currentUser = $userQuery->fetch(PDO::FETCH_ASSOC);
if (!$currentUser) {
    classementRespond(['error' => 'Compte introuvable.'], 401);
}
$isAdmin = (int) $currentUser['admin'] === 1;
$uploadDirectory = __DIR__ . '/../data/classement_photos';

if (isset($_GET['photo'])) {
    $filename = basename((string) $_GET['photo']);
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        classementRespond(['error' => 'Photo introuvable.'], 404);
    }
    $photoPath = $uploadDirectory . '/' . $filename;
    if (!is_file($photoPath)) {
        classementRespond(['error' => 'Photo introuvable.'], 404);
    }
    $mimeByExtension = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    header('Content-Type: ' . $mimeByExtension[pathinfo($filename, PATHINFO_EXTENSION)]);
    header('Content-Length: ' . (string) filesize($photoPath));
    readfile($photoPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $data = rankingStorageRead($pdo);
    } catch (Throwable $error) {
        error_log('[classement] ' . $error->getMessage());
        classementRespond(['error' => 'Impossible de lire le classement.'], 500);
    }

    if (isset($_GET['users'])) {
        $query = trim((string) $_GET['users']);
        if (mb_strlen($query) < 2) {
            classementRespond(['users' => []]);
        }
                $searchTerms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $searchConditions = [];
                $searchParams = [$userId];
                foreach ($searchTerms as $term) {
                        $searchConditions[] = '(firstname LIKE ? OR lastname LIKE ?)';
                        $termPattern = '%' . $term . '%';
                        $searchParams[] = $termPattern;
                        $searchParams[] = $termPattern;
                }
        $userSearch = $pdo->prepare(
            'SELECT id, firstname, lastname
             FROM account_wtc
             WHERE ban = 0 AND id <> ?
                             AND ' . implode(' AND ', $searchConditions) . '
             ORDER BY firstname, lastname
             LIMIT 10'
        );
                $userSearch->execute($searchParams);
        $users = [];
        foreach ($userSearch->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $users[] = [
                'id' => (int) $user['id'],
                'firstname' => (string) $user['firstname'],
                'last_initial' => mb_substr((string) $user['lastname'], 0, 1),
                'label' => (string) $user['firstname'] . ' ' . (string) $user['lastname'],
            ];
        }
        classementRespond(['users' => $users]);
    }

    $categoryId = trim((string) ($_GET['category'] ?? ''));
    $subcategoryId = trim((string) ($_GET['subcategory'] ?? ''));
    if ($categoryId === '') {
        $personalBest = [];
        $recordCountsByCategory = [];
        $recordCountsBySubcategory = [];
        foreach ($data['records'] as $record) {
            $recordCategoryId = (string) ($record['category_id'] ?? '');
            $recordSubcategoryId = (string) ($record['subcategory_id'] ?? '');
            $recordCountsByCategory[$recordCategoryId] = ($recordCountsByCategory[$recordCategoryId] ?? 0) + 1;
            if ($recordSubcategoryId !== '') {
                $recordCountsBySubcategory[$recordCategoryId][$recordSubcategoryId] = ($recordCountsBySubcategory[$recordCategoryId][$recordSubcategoryId] ?? 0) + 1;
            }
        }
        $rankingRows = classementExpandRecords($data['records']);
        foreach ($rankingRows as $record) {
            if ((int) ($record['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $categoryKey = (string) $record['category_id'];
            if (!isset($personalBest[$categoryKey]) || $record['time_seconds'] < $personalBest[$categoryKey]['time_seconds']) {
                $personalBest[$categoryKey] = $record;
            }
        }
        foreach ($data['categories'] as &$category) {
            $category['subcategories'] = array_values($category['subcategories'] ?? []);
            $categoryIdKey = (string) $category['id'];
            $category['record_count'] = $recordCountsByCategory[$categoryIdKey] ?? 0;
            foreach ($category['subcategories'] as &$subcategory) {
                $subcategory['record_count'] = $recordCountsBySubcategory[$categoryIdKey][(string) $subcategory['id']] ?? 0;
            }
            unset($subcategory);
            $category['personal_best'] = $personalBest[(string) $category['id']] ?? null;
        }
        unset($category);
        classementRespond(['categories' => array_values($data['categories']), 'is_admin' => $isAdmin]);
    }

    $category = null;
    foreach ($data['categories'] as $candidate) {
        if ((string) $candidate['id'] === $categoryId) {
            $category = $candidate;
            break;
        }
    }
    if ($category === null) {
        classementRespond(['error' => 'Cette catégorie n’existe plus.'], 404);
    }
    $category['subcategories'] = array_values($category['subcategories'] ?? []);
    $recordCountsBySubcategory = [];
    foreach ($data['records'] as $record) {
        if ((string) ($record['category_id'] ?? '') !== $categoryId) {
            continue;
        }
        $recordSubcategoryId = (string) ($record['subcategory_id'] ?? '');
        if ($recordSubcategoryId !== '') {
            $recordCountsBySubcategory[$recordSubcategoryId] = ($recordCountsBySubcategory[$recordSubcategoryId] ?? 0) + 1;
        }
    }
    foreach ($category['subcategories'] as &$subcategory) {
        $subcategory['record_count'] = $recordCountsBySubcategory[(string) $subcategory['id']] ?? 0;
    }
    unset($subcategory);
    $selectedCategory = $category;
    if ($subcategoryId !== '') {
        $selectedCategory = null;
        foreach ($category['subcategories'] as $subcategory) {
            if ((string) $subcategory['id'] === $subcategoryId) {
                $selectedCategory = $subcategory;
                break;
            }
        }
        if ($selectedCategory === null) {
            classementRespond(['error' => 'Cette sous-catégorie n’existe plus.'], 404);
        }
    }

    $categoryEvents = array_values(array_filter($data['records'], static function ($record) use ($categoryId, $subcategoryId) {
        if ((string) $record['category_id'] !== $categoryId) {
            return false;
        }
        return $subcategoryId === '' || (string) ($record['subcategory_id'] ?? '') === $subcategoryId;
    }));
    $records = array_map(static function ($record) {
        $record['participants'] = classementParticipants($record);
        return $record;
    }, $categoryEvents);
    usort($records, static function ($left, $right) {
        return ($left['time_seconds'] <=> $right['time_seconds'])
            ?: strcmp((string) $left['created_at'], (string) $right['created_at']);
    });
    $totalCount = count($records);
    if ($subcategoryId === '' && $category['subcategories']) {
        $records = array_slice($records, 0, 5);
    }
    classementRespond([
        'category' => $selectedCategory,
        'parent_category' => $subcategoryId === '' ? null : ['id' => $category['id'], 'name' => $category['name']],
        'subcategory_id' => $subcategoryId,
        'subcategories' => $subcategoryId === '' ? $category['subcategories'] : [],
        'records' => $records,
        'total_count' => $totalCount,
        'is_admin' => $isAdmin,
        'user_id' => $userId,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    classementRespond(['error' => 'Méthode non autorisée.'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!is_string($csrfToken) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    classementRespond(['error' => 'Session expirée. Recharge la page et réessaie.'], 403);
}

$action = (string) ($_POST['action'] ?? '');
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($input)) {
        $action = (string) ($input['action'] ?? $action);
        $_POST = array_merge($_POST, $input);
    }
}
if (in_array($action, ['create_category', 'add_subcategory', 'delete_category', 'delete_record', 'delete_subcategory'], true) && !$isAdmin) {
    classementRespond(['error' => 'Action réservée aux admins.'], 403);
}

$savedPhotos = [];
$rankingLockHeld = false;
try {
    if ($action === 'create_record') {
        $competition = trim((string) ($_POST['competition'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $categoryId = trim((string) ($_POST['category_id'] ?? ''));
        $subcategoryId = trim((string) ($_POST['subcategory_id'] ?? ''));
        $partnerIdsInput = trim((string) ($_POST['partner_ids'] ?? ''));
        $hours = filter_var($_POST['hours'] ?? null, FILTER_VALIDATE_INT);
        $minutes = filter_var($_POST['minutes'] ?? null, FILTER_VALIDATE_INT);
        $seconds = filter_var($_POST['seconds'] ?? null, FILTER_VALIDATE_INT);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($competition === '' || mb_strlen($competition) > 120 || $categoryId === ''
            || !$date || $date->format('Y-m-d') !== $eventDate
            || (is_array($dateErrors) && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            || $hours === false || $hours < 0 || $hours > 99
            || $minutes === false || $minutes < 0 || $minutes > 59
            || $seconds === false || $seconds < 0 || $seconds > 59
            || ($hours * 3600 + $minutes * 60 + $seconds) <= 0) {
            classementRespond(['error' => 'Vérifie la compétition, la date et le temps saisi.'], 400);
        }

        if ($partnerIdsInput !== '') {
            $partnerIds = json_decode($partnerIdsInput, true);
            if (!is_array($partnerIds)) {
                classementRespond(['error' => 'La liste des participants est invalide.'], 400);
            }
        } else {
            $legacyPartnerId = trim((string) ($_POST['partner_id'] ?? ''));
            $partnerIds = $legacyPartnerId === '' ? [] : [$legacyPartnerId];
        }
        if (count($partnerIds) > 20) {
            classementRespond(['error' => 'Tu peux ajouter au maximum 20 participants à une performance.'], 400);
        }
        $partners = [];
        $seenPartnerIds = [];
        $seenExternalNames = [];
        foreach ($partnerIds as $rawPartnerId) {
            if (is_array($rawPartnerId)) {
                $firstname = is_string($rawPartnerId['firstname'] ?? null) ? trim($rawPartnerId['firstname']) : '';
                $lastname = is_string($rawPartnerId['lastname'] ?? null) ? trim($rawPartnerId['lastname']) : '';
                if ($firstname === '' || $lastname === '' || mb_strlen($firstname) > 80 || mb_strlen($lastname) > 80) {
                    classementRespond(['error' => 'Saisis un prénom et un nom valides pour le participant externe.'], 400);
                }
                $externalKey = mb_strtolower($firstname . ' ' . $lastname);
                if (isset($seenExternalNames[$externalKey])) {
                    continue;
                }
                $seenExternalNames[$externalKey] = true;
                $partners[] = [
                    'user_id' => 0,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'last_initial' => mb_substr($lastname, 0, 1),
                    'external' => true,
                ];
                continue;
            }
            $partnerId = filter_var($rawPartnerId, FILTER_VALIDATE_INT);
            if ($partnerId === false || $partnerId <= 0 || $partnerId === $userId) {
                classementRespond(['error' => 'Choisis des adhérents valides pour partager cette performance.'], 400);
            }
            if (isset($seenPartnerIds[$partnerId])) {
                continue;
            }
            $seenPartnerIds[$partnerId] = true;
            $partnerQuery = $pdo->prepare('SELECT id, firstname, lastname FROM account_wtc WHERE id = ? AND ban = 0');
            $partnerQuery->execute([$partnerId]);
            $partner = $partnerQuery->fetch(PDO::FETCH_ASSOC);
            if (!$partner) {
                classementRespond(['error' => 'Cet adhérent n’est plus disponible.'], 404);
            }
            $partners[] = [
                'user_id' => (int) $partner['id'],
                'firstname' => (string) $partner['firstname'],
                'lastname' => (string) $partner['lastname'],
                'last_initial' => mb_substr((string) $partner['lastname'], 0, 1),
            ];
        }
        $savedPhotos = classementSavePhotos($_FILES['photos'] ?? [], $uploadDirectory);
    } elseif ($action === 'create_category') {
        $categoryName = trim((string) ($_POST['name'] ?? ''));
        if ($categoryName === '' || mb_strlen($categoryName) > 80) {
            classementRespond(['error' => 'Le nom doit contenir entre 1 et 80 caractères.'], 400);
        }
        $rawSubcategories = trim((string) ($_POST['subcategories'] ?? ''));
        $subcategoryNames = $rawSubcategories === '' ? [] : json_decode($rawSubcategories, true);
        if (!is_array($subcategoryNames) || count($subcategoryNames) > 100) {
            classementRespond(['error' => 'La liste des sous-catégories est invalide.'], 400);
        }
        $cleanSubcategoryNames = [];
        $seenSubcategoryNames = [];
        foreach ($subcategoryNames as $subcategoryName) {
            if (!is_string($subcategoryName)) {
                classementRespond(['error' => 'Chaque sous-catégorie doit avoir un nom valide.'], 400);
            }
            $subcategoryName = trim($subcategoryName);
            if ($subcategoryName === '' || mb_strlen($subcategoryName) > 80) {
                classementRespond(['error' => 'Chaque nom de sous-catégorie doit contenir entre 1 et 80 caractères.'], 400);
            }
            $normalizedName = mb_strtolower($subcategoryName);
            if (isset($seenSubcategoryNames[$normalizedName])) {
                classementRespond(['error' => 'Les sous-catégories doivent avoir des noms différents.'], 400);
            }
            $seenSubcategoryNames[$normalizedName] = true;
            $cleanSubcategoryNames[] = $subcategoryName;
        }
        if (isset($_POST['has_subcategories']) && $_POST['has_subcategories'] === '1' && !$cleanSubcategoryNames) {
            classementRespond(['error' => 'Ajoute au moins une sous-catégorie ou décoche cette option.'], 400);
        }
    } elseif ($action === 'add_subcategory') {
        $categoryId = trim((string) ($_POST['category_id'] ?? ''));
        $subcategoryName = trim((string) ($_POST['name'] ?? ''));
        if ($categoryId === '' || $subcategoryName === '' || mb_strlen($subcategoryName) > 80) {
            classementRespond(['error' => 'Saisis un nom de sous-catégorie valide.'], 400);
        }
    } elseif ($action === 'delete_subcategory') {
        $categoryId = trim((string) ($_POST['category_id'] ?? ''));
        $subcategoryId = trim((string) ($_POST['subcategory_id'] ?? ''));
        if ($categoryId === '' || $subcategoryId === '') {
            classementRespond(['error' => 'Cette sous-catégorie n’existe plus.'], 404);
        }
    } elseif ($action === 'edit_time') {
        $recordId = trim((string) ($_POST['record_id'] ?? ''));
        $hours = filter_var($_POST['hours'] ?? null, FILTER_VALIDATE_INT);
        $minutes = filter_var($_POST['minutes'] ?? null, FILTER_VALIDATE_INT);
        $seconds = filter_var($_POST['seconds'] ?? null, FILTER_VALIDATE_INT);
        if ($recordId === '' || $hours === false || $hours < 0 || $hours > 99
            || $minutes === false || $minutes < 0 || $minutes > 59
            || $seconds === false || $seconds < 0 || $seconds > 59
            || ($hours * 3600 + $minutes * 60 + $seconds) <= 0) {
            classementRespond(['error' => 'Vérifie le temps saisi.'], 400);
        }
    } elseif ($action === 'add_participants') {
        $recordId = trim((string) ($_POST['record_id'] ?? ''));
        $partnerIdsInput = trim((string) ($_POST['partner_ids'] ?? ''));
        $partnerIds = $partnerIdsInput === '' ? null : json_decode($partnerIdsInput, true);
        if ($recordId === '' || !is_array($partnerIds) || !$partnerIds) {
            classementRespond(['error' => 'Choisis au moins un participant à ajouter.'], 400);
        }
        if (count($partnerIds) > 20) {
            classementRespond(['error' => 'Tu peux ajouter au maximum 20 participants à une performance.'], 400);
        }
        $partnersToAdd = [];
        $seenPartnerIds = [];
        $seenExternalNames = [];
        foreach ($partnerIds as $rawPartnerId) {
            if (is_array($rawPartnerId)) {
                $firstname = is_string($rawPartnerId['firstname'] ?? null) ? trim($rawPartnerId['firstname']) : '';
                $lastname = is_string($rawPartnerId['lastname'] ?? null) ? trim($rawPartnerId['lastname']) : '';
                if ($firstname === '' || $lastname === '' || mb_strlen($firstname) > 80 || mb_strlen($lastname) > 80) {
                    classementRespond(['error' => 'Saisis un prénom et un nom valides pour le participant externe.'], 400);
                }
                $externalKey = mb_strtolower($firstname . ' ' . $lastname);
                if (isset($seenExternalNames[$externalKey])) {
                    continue;
                }
                $seenExternalNames[$externalKey] = true;
                $partnersToAdd[] = [
                    'user_id' => 0,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'last_initial' => mb_substr($lastname, 0, 1),
                    'external' => true,
                ];
                continue;
            }
            $partnerId = filter_var($rawPartnerId, FILTER_VALIDATE_INT);
            if ($partnerId === false || $partnerId <= 0 || $partnerId === $userId) {
                classementRespond(['error' => 'Choisis des adhérents valides à ajouter.'], 400);
            }
            if (isset($seenPartnerIds[$partnerId])) {
                continue;
            }
            $seenPartnerIds[$partnerId] = true;
            $partnerQuery = $pdo->prepare('SELECT id, firstname, lastname FROM account_wtc WHERE id = ? AND ban = 0');
            $partnerQuery->execute([$partnerId]);
            $partner = $partnerQuery->fetch(PDO::FETCH_ASSOC);
            if (!$partner) {
                classementRespond(['error' => 'Cet adhérent n’est plus disponible.'], 404);
            }
            $partnersToAdd[] = [
                'user_id' => (int) $partner['id'],
                'firstname' => (string) $partner['firstname'],
                'lastname' => (string) $partner['lastname'],
                'last_initial' => mb_substr((string) $partner['lastname'], 0, 1),
            ];
        }
    } elseif (!in_array($action, ['delete_category', 'delete_record', 'remove_participation'], true)) {
        classementRespond(['error' => 'Action inconnue.'], 400);
    }

    $rankingLock = $pdo->query("SELECT GET_LOCK('wtc_ranking_storage', 5)")->fetchColumn();
    if ((int) $rankingLock !== 1) {
        classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
        classementRespond(['error' => 'Le classement est temporairement indisponible.'], 500);
    }
    $rankingLockHeld = true;
    $data = rankingStorageRead($pdo);
    $response = null;
    $photosToRemove = [];

    if ($action === 'create_category') {
        foreach ($data['categories'] as $category) {
            if (mb_strtolower($category['name']) === mb_strtolower($categoryName)) {
                classementRespond(['error' => 'Cette catégorie existe déjà.'], 409);
            }
        }
        $subcategories = [];
        foreach ($cleanSubcategoryNames as $subcategoryName) {
            $subcategories[] = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $subcategoryName,
                'created_at' => date(DATE_ATOM),
            ];
        }
        $category = ['id' => bin2hex(random_bytes(8)), 'name' => $categoryName, 'subcategories' => $subcategories, 'created_at' => date(DATE_ATOM)];
        $data['categories'][] = $category;
        $response = ['category' => $category];
    } elseif ($action === 'add_subcategory') {
        $categoryIndex = null;
        foreach ($data['categories'] as $index => $category) {
            if ((string) $category['id'] === $categoryId) {
                $categoryIndex = $index;
                break;
            }
        }
        if ($categoryIndex === null) {
            classementRespond(['error' => 'Cette catégorie n’existe plus.'], 404);
        }
        $subcategories = $data['categories'][$categoryIndex]['subcategories'] ?? [];
        if (count($subcategories) >= 100) {
            classementRespond(['error' => 'La catégorie ne peut pas dépasser 100 sous-catégories.'], 400);
        }
        foreach ($subcategories as $subcategory) {
            if (mb_strtolower((string) $subcategory['name']) === mb_strtolower($subcategoryName)) {
                classementRespond(['error' => 'Cette sous-catégorie existe déjà.'], 409);
            }
        }
        $subcategory = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $subcategoryName,
            'created_at' => date(DATE_ATOM),
        ];
        $data['categories'][$categoryIndex]['subcategories'] = $subcategories;
        $data['categories'][$categoryIndex]['subcategories'][] = $subcategory;
        $response = ['subcategory' => $subcategory];
    } elseif ($action === 'delete_subcategory') {
        $categoryIndex = null;
        foreach ($data['categories'] as $index => $category) {
            if ((string) $category['id'] === $categoryId) {
                $categoryIndex = $index;
                break;
            }
        }
        if ($categoryIndex === null) {
            classementRespond(['error' => 'Cette catégorie n’existe plus.'], 404);
        }
        $subcategories = $data['categories'][$categoryIndex]['subcategories'] ?? [];
        $subcategoryFound = false;
        foreach ($subcategories as $subcategory) {
            if ((string) $subcategory['id'] === $subcategoryId) {
                $subcategoryFound = true;
                break;
            }
        }
        if (!$subcategoryFound) {
            classementRespond(['error' => 'Cette sous-catégorie n’existe plus.'], 404);
        }
        $matchingRecords = array_values(array_filter($data['records'], static fn($record) =>
            (string) ($record['category_id'] ?? '') === $categoryId
            && (string) ($record['subcategory_id'] ?? '') === $subcategoryId
        ));
        $photosToRemove = $matchingRecords;
        $data['records'] = array_values(array_filter($data['records'], static fn($record) =>
            (string) ($record['category_id'] ?? '') !== $categoryId
            || (string) ($record['subcategory_id'] ?? '') !== $subcategoryId
        ));
        $data['categories'][$categoryIndex]['subcategories'] = array_values(array_filter($subcategories, static fn($subcategory) => (string) $subcategory['id'] !== $subcategoryId));
        $response = ['deleted' => true, 'deleted_record_count' => count($matchingRecords)];
    } elseif ($action === 'create_record') {
        $targetCategory = null;
        foreach ($data['categories'] as $category) {
            if ((string) $category['id'] === $categoryId) {
                $targetCategory = $category;
                break;
            }
        }
        if ($targetCategory === null) {
            classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
            classementRespond(['error' => 'Cette catégorie n’existe plus.'], 404);
        }
        $targetSubcategory = null;
        foreach (($targetCategory['subcategories'] ?? []) as $subcategory) {
            if ((string) $subcategory['id'] === $subcategoryId) {
                $targetSubcategory = $subcategory;
                break;
            }
        }
        if ($subcategoryId !== '' && $targetSubcategory === null) {
            classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
            classementRespond(['error' => 'Cette sous-catégorie n’existe plus.'], 404);
        }
        if (!$isAdmin && !empty($targetCategory['subcategories']) && $targetSubcategory === null) {
            classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
            classementRespond(['error' => 'Choisis une sous-catégorie avant d’ajouter un temps.'], 400);
        }
        $participants = [[
            'user_id' => $userId,
            'firstname' => (string) $currentUser['firstname'],
            'last_initial' => mb_substr((string) $currentUser['lastname'], 0, 1),
        ]];
        foreach ($partners as $partner) {
            $participants[] = [
                'user_id' => (int) $partner['user_id'],
                'firstname' => (string) $partner['firstname'],
                'lastname' => (string) $partner['lastname'],
                'last_initial' => (string) $partner['last_initial'],
            ] + (!empty($partner['external']) ? ['external' => true] : []);
        }
        $record = [
            'id' => bin2hex(random_bytes(12)),
            'category_id' => $categoryId,
            'subcategory_id' => $targetSubcategory['id'] ?? null,
            'owner_id' => $userId,
            'participants' => $participants,
            'competition' => $competition,
            'event_date' => $eventDate,
            'time_seconds' => $hours * 3600 + $minutes * 60 + $seconds,
            'photos' => $savedPhotos,
            'created_at' => date(DATE_ATOM),
        ];
        $data['records'][] = $record;
        $response = ['record' => $record];
    } elseif ($action === 'delete_category') {
        $categoryId = trim((string) ($_POST['category_id'] ?? ''));
        $categoryFound = false;
        foreach ($data['categories'] as $category) {
            if ((string) $category['id'] === $categoryId) {
                $categoryFound = true;
                break;
            }
        }
        if (!$categoryFound) {
            classementRespond(['error' => 'Cette catégorie n’existe plus.'], 404);
        }
        $photosToRemove = array_values(array_filter($data['records'], static fn($record) => (string) $record['category_id'] === $categoryId));
        $data['categories'] = array_values(array_filter($data['categories'], static fn($category) => (string) $category['id'] !== $categoryId));
        $data['records'] = array_values(array_filter($data['records'], static fn($record) => (string) $record['category_id'] !== $categoryId));
        $response = ['deleted' => true];
    } elseif ($action === 'edit_time') {
        $recordIndex = null;
        foreach ($data['records'] as $index => $record) {
            if ((string) $record['id'] === $recordId) {
                $recordIndex = $index;
                break;
            }
        }
        if ($recordIndex === null) {
            classementRespond(['error' => 'Cette performance n’existe plus.'], 404);
        }
        $participants = classementParticipants($data['records'][$recordIndex]);
        if (!in_array($userId, array_map(static fn($participant) => (int) $participant['user_id'], $participants), true)) {
            classementRespond(['error' => 'Tu ne peux modifier que ta propre participation.'], 403);
        }
        $data['records'][$recordIndex]['time_seconds'] = $hours * 3600 + $minutes * 60 + $seconds;
        $response = ['updated' => true];
    } elseif ($action === 'add_participants') {
        $recordIndex = null;
        foreach ($data['records'] as $index => $record) {
            if ((string) $record['id'] === $recordId) {
                $recordIndex = $index;
                break;
            }
        }
        if ($recordIndex === null) {
            classementRespond(['error' => 'Cette performance n’existe plus.'], 404);
        }
        $participants = classementParticipants($data['records'][$recordIndex]);
        if (!in_array($userId, array_map(static fn($participant) => (int) $participant['user_id'], $participants), true)) {
            classementRespond(['error' => 'Tu ne peux ajouter des participants qu’à ta propre participation.'], 403);
        }
        foreach ($partnersToAdd as $partner) {
            $alreadyIncluded = false;
            foreach ($participants as $participant) {
                if ((int) ($partner['user_id'] ?? 0) > 0
                    && (int) ($participant['user_id'] ?? 0) === (int) $partner['user_id']) {
                    $alreadyIncluded = true;
                    break;
                }
                if (!empty($partner['external']) && !empty($participant['external'])
                    && mb_strtolower((string) ($participant['firstname'] ?? '') . ' ' . (string) ($participant['lastname'] ?? ''))
                        === mb_strtolower((string) $partner['firstname'] . ' ' . (string) $partner['lastname'])) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) {
                $participants[] = $partner;
            }
        }
        if (count($participants) === count(classementParticipants($data['records'][$recordIndex]))) {
            classementRespond(['error' => 'Ces participants figurent déjà sur cette performance.'], 409);
        }
        $data['records'][$recordIndex]['participants'] = $participants;
        $response = ['updated' => true, 'participants' => $participants];
    } elseif ($action === 'remove_participation') {
        $recordId = trim((string) ($_POST['record_id'] ?? ''));
        $recordIndex = null;
        foreach ($data['records'] as $index => $record) {
            if ((string) $record['id'] === $recordId) {
                $recordIndex = $index;
                break;
            }
        }
        if ($recordIndex === null) {
            classementRespond(['error' => 'Cette performance n’existe plus.'], 404);
        }
        $participants = classementParticipants($data['records'][$recordIndex]);
        $remainingParticipants = array_values(array_filter($participants, static fn($participant) => (int) $participant['user_id'] !== $userId));
        if (count($remainingParticipants) === count($participants)) {
            classementRespond(['error' => 'Tu ne peux retirer que ta propre participation.'], 403);
        }
        if (!$remainingParticipants) {
            $photosToRemove[] = $data['records'][$recordIndex];
            unset($data['records'][$recordIndex]);
            $data['records'] = array_values($data['records']);
        } else {
            $data['records'][$recordIndex]['participants'] = $remainingParticipants;
            if ((int) ($data['records'][$recordIndex]['owner_id'] ?? 0) === $userId) {
                $data['records'][$recordIndex]['owner_id'] = (int) $remainingParticipants[0]['user_id'];
            }
        }
        $response = ['removed' => true];
    } else {
        $recordId = trim((string) ($_POST['record_id'] ?? ''));
        $recordFound = false;
        foreach ($data['records'] as $record) {
            if ((string) $record['id'] === $recordId) {
                $recordFound = $record;
                break;
            }
        }
        if ($recordFound === false) {
            classementRespond(['error' => 'Cette performance n’existe plus.'], 404);
        }
        $photosToRemove[] = $recordFound;
        $data['records'] = array_values(array_filter($data['records'], static fn($record) => (string) $record['id'] !== $recordId));
        $response = ['deleted' => true];
    }

    rankingStorageReplace($pdo, $data);
    classementReleaseLock($pdo, $rankingLockHeld);
    classementRemovePhotos($photosToRemove, $uploadDirectory);
    classementRespond(['success' => true] + $response);
} catch (InvalidArgumentException $error) {
    classementReleaseLock($pdo, $rankingLockHeld);
    classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
    classementRespond(['error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    classementReleaseLock($pdo, $rankingLockHeld);
    classementRemovePhotos([['photos' => $savedPhotos]], $uploadDirectory);
    error_log('[classement] ' . $error->getMessage());
    classementRespond(['error' => 'Une erreur est survenue pendant l’enregistrement.'], 500);
}