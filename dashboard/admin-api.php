<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
dashboardSendHeaders();
dashboardStartSession();
dashboardRequireAuth();

$pdo = dashboardDatabase();
if (!$pdo instanceof PDO) {
    dashboardJson(['error' => 'Connexion à la base de données indisponible.'], 503);
}

function dashboardAdminRespondError(Throwable $error): never
{
    error_log('[dashboard-admin] ' . $error->getMessage());
    dashboardJson(['error' => 'Stockage admin indisponible. Appliquez admin-upgrade.sql et vérifiez les droits SQL du dashboard.'], 503);
}

function dashboardAdminLinks(): array
{
    return [
        'hero_inscription' => ['label' => 'Inscription en ligne', 'title' => 'Bouton principal d’inscription', 'url' => 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2026-2027', 'order' => 1],
        'card_adhesion' => ['label' => 'Adhésion', 'title' => 'Carte d’adhésion', 'url' => 'https://www.helloasso.com/associations/warriors-training-club/adhesions/formulaire-d-inscription-2026-2027', 'order' => 2],
        'card_boutique_barres' => ['label' => 'Boutique', 'title' => 'Barres de céréales Les Craq\'s', 'url' => 'https://www.helloasso.com/associations/judo-club-mormant/boutiques/barres-de-cereales-artisanales', 'order' => 3],
        'card_boutique_vetements' => ['label' => 'Boutique', 'title' => 'Vêtements Warriors', 'url' => 'https://market-factory.fr/warriors-training-club/', 'order' => 5],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $section = (string) ($_GET['section'] ?? 'overview');
    try {
        if ($section === 'overview') {
            dashboardJson(['data' => [
                'users' => (int) $pdo->query('SELECT COUNT(*) FROM account_wtc')->fetchColumn(),
                'admins' => (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE admin = 1')->fetchColumn(),
                'online' => (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)')->fetchColumn(),
                'reports' => (int) $pdo->query('SELECT COUNT(*) FROM dashboard_reports')->fetchColumn(),
                'maintenance' => (bool) $pdo->query('SELECT COALESCE(MAX(maintenance), 0) FROM account_wtc')->fetchColumn(),
            ]]);
        }
        if ($section === 'users') {
            $users = $pdo->query("SELECT id, firstname, lastname, email, admin, gerer_seances, ban, maintenance, email_verified, last_seen, (last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS is_online FROM account_wtc ORDER BY lastname, firstname LIMIT 1000")->fetchAll();
            dashboardJson(['data' => $users]);
        }
        if ($section === 'online') {
            $onlineIds = $pdo->query('SELECT id FROM account_wtc WHERE last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= 300')->fetchAll(PDO::FETCH_COLUMN);
            dashboardJson(['data' => array_map('intval', $onlineIds)]);
        }
        if ($section === 'reports') {
            $reports = $pdo->query('SELECT id, email, message, created_at FROM dashboard_reports ORDER BY created_at DESC LIMIT 500')->fetchAll();
            dashboardJson(['data' => $reports]);
        }
        if ($section === 'audit') {
            $logs = $pdo->query("SELECT l.id, l.created_at, l.query_type, l.table_name, l.statement, l.status, l.source_page, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.lastname)), ''), IF(l.actor_id IS NULL, 'Visiteur', CONCAT('Compte #', l.actor_id))) AS actor_name FROM sql_action_logs l LEFT JOIN account_wtc a ON a.id = l.actor_id ORDER BY l.id DESC LIMIT 200")->fetchAll();
            dashboardJson(['data' => $logs]);
        }
        if ($section === 'links') {
            $links = dashboardAdminLinks();
            $rows = $pdo->query('SELECT link_key, url FROM index_links')->fetchAll();
            foreach ($rows as $row) {
                if (isset($links[$row['link_key']])) {
                    $links[$row['link_key']]['url'] = $row['url'];
                }
            }
            dashboardJson(['data' => $links]);
        }
        if ($section === 'commits') {
            dashboardJson(['data' => $pdo->query('SELECT sha AS id, message, url, author, committed_at AS timestamp, repo FROM dashboard_commits ORDER BY committed_at DESC LIMIT 100')->fetchAll()]);
        }
        if ($section === 'commit_detail') {
            $sha = strtolower((string) ($_GET['sha'] ?? ''));
            if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
                dashboardJson(['error' => 'Identifiant de commit invalide.'], 400);
            }
            $repository = 'MeTtHa-Matt/Warriors-Training-Club-App';
            $headers = "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github+json\r\n";
            $token = trim(dashboardSetting('GITHUB_TOKEN', dashboardSetting('GITHUB_API_TOKEN')));
            if ($token !== '') {
                $headers .= 'Authorization: Bearer ' . $token . "\r\n";
            }
            $context = stream_context_create(['http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 8, 'ignore_errors' => true]]);
            $raw = @file_get_contents('https://api.github.com/repos/' . $repository . '/commits/' . rawurlencode($sha), false, $context);
            $item = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($item) || isset($item['message'])) {
                dashboardJson(['error' => 'Détails GitHub temporairement indisponibles.'], 502);
            }
            $files = array_map(static fn (array $file): array => [
                'filename' => (string) ($file['filename'] ?? ''),
                'status' => (string) ($file['status'] ?? ''),
                'additions' => (int) ($file['additions'] ?? 0),
                'deletions' => (int) ($file['deletions'] ?? 0),
            ], is_array($item['files'] ?? null) ? $item['files'] : []);
            dashboardJson(['data' => ['files' => $files]]);
        }
        if ($section === 'settings') {
            $settings = $pdo->query("SELECT setting_key, value FROM app_settings WHERE setting_key IN ('session_timeout_seconds', 'session_modal_threshold_seconds')")->fetchAll(PDO::FETCH_KEY_PAIR);
            dashboardJson(['data' => [
                'session_timeout_seconds' => (int) ($settings['session_timeout_seconds'] ?? 1209600),
                'session_modal_threshold_seconds' => (int) ($settings['session_modal_threshold_seconds'] ?? 86400),
                'maintenance' => (bool) $pdo->query('SELECT COALESCE(MAX(maintenance), 0) FROM account_wtc')->fetchColumn(),
            ]]);
        }
        if ($section === 'mail') {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM account_wtc WHERE ban = 0 AND accept_email = 1 AND email <> ''")->fetchColumn();
            dashboardJson(['data' => ['recipients' => $count]]);
        }
        dashboardJson(['error' => 'Section inconnue.'], 404);
    } catch (Throwable $error) {
        dashboardAdminRespondError($error);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    dashboardJson(['error' => 'Méthode non autorisée.'], 405);
}
if (!dashboardCheckCsrf()) {
    dashboardJson(['error' => 'Jeton CSRF invalide.'], 403);
}

$action = (string) ($_POST['action'] ?? '');
$targetId = filter_var($_POST['target_id'] ?? 0, FILTER_VALIDATE_INT);
try {
    if (in_array($action, ['toggle_admin', 'toggle_gerer_seances', 'toggle_ban', 'verify_email', 'delete_account'], true)) {
        if (!$targetId || $targetId < 1) {
            dashboardJson(['error' => 'Compte invalide.'], 400);
        }
        $userQuery = $pdo->prepare('SELECT id, admin FROM account_wtc WHERE id = ?');
        $userQuery->execute([$targetId]);
        $target = $userQuery->fetch();
        if (!$target) {
            dashboardJson(['error' => 'Compte introuvable.'], 404);
        }
        if ($action === 'toggle_admin') {
            $pdo->beginTransaction();
            $current = (int) $target['admin'];
            $admins = $pdo->query('SELECT id FROM account_wtc WHERE admin = 1 FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            if ($current === 1 && count($admins) <= 1) {
                $pdo->rollBack();
                dashboardJson(['error' => 'Il doit rester au moins un administrateur.'], 409);
            }
            $update = $pdo->prepare('UPDATE account_wtc SET admin = ? WHERE id = ?');
            $update->execute([$current ? 0 : 1, $targetId]);
            $pdo->commit();
        } elseif ($action === 'toggle_gerer_seances') {
            $update = $pdo->prepare('UPDATE account_wtc SET gerer_seances = 1 - gerer_seances WHERE id = ?');
            $update->execute([$targetId]);
        } elseif ($action === 'toggle_ban') {
            $update = $pdo->prepare('UPDATE account_wtc SET ban = 1 - ban WHERE id = ?');
            $update->execute([$targetId]);
        } elseif ($action === 'verify_email') {
            $update = $pdo->prepare('UPDATE account_wtc SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?');
            $update->execute([$targetId]);
        } else {
            $pdo->beginTransaction();
            if ((int) $target['admin'] === 1) {
                $admins = $pdo->query('SELECT id FROM account_wtc WHERE admin = 1 FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
                if (count($admins) <= 1) {
                    $pdo->rollBack();
                    dashboardJson(['error' => 'Impossible de supprimer le dernier administrateur.'], 409);
                }
            }
            $delete = $pdo->prepare('DELETE FROM account_wtc WHERE id = ?');
            $delete->execute([$targetId]);
            $pdo->commit();
        }
        dashboardJson(['success' => true]);
    }

    if ($action === 'set_maintenance') {
        $enabled = (int) ($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
        $update = $pdo->prepare('UPDATE account_wtc SET maintenance = ?');
        $update->execute([$enabled]);
        dashboardJson(['success' => true]);
    }

    if ($action === 'save_links') {
        $links = dashboardAdminLinks();
        $pdo->beginTransaction();
        $upsert = $pdo->prepare('INSERT INTO index_links (link_key, label, title, url, display_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), title = VALUES(title), url = VALUES(url), display_order = VALUES(display_order)');
        foreach ($links as $key => $link) {
            $url = trim((string) ($_POST[$key] ?? ''));
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
                $pdo->rollBack();
                dashboardJson(['error' => 'Chaque lien doit être une URL HTTP ou HTTPS valide.'], 400);
            }
            $upsert->execute([$key, $link['label'], $link['title'], $url, $link['order']]);
        }
        $pdo->commit();
        dashboardJson(['success' => true]);
    }

    if ($action === 'save_settings') {
        $timeout = filter_var($_POST['session_timeout_seconds'] ?? null, FILTER_VALIDATE_INT);
        $warning = filter_var($_POST['session_modal_threshold_seconds'] ?? null, FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < 1 || $timeout > 315360000 || $warning === false || $warning < 0 || $warning > 315360000) {
            dashboardJson(['error' => 'Durée de session invalide.'], 400);
        }
        $pdo->beginTransaction();
        $save = $pdo->prepare('INSERT INTO app_settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $save->execute(['session_timeout_seconds', (string) $timeout]);
        $save->execute(['session_modal_threshold_seconds', (string) $warning]);
        $pdo->commit();
        dashboardJson(['success' => true]);
    }

    if ($action === 'delete_report') {
        $reportId = trim((string) ($_POST['report_id'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $reportId)) {
            dashboardJson(['error' => 'Signalement invalide.'], 400);
        }
        $delete = $pdo->prepare('DELETE FROM dashboard_reports WHERE id = ?');
        $delete->execute([$reportId]);
        dashboardJson(['success' => true]);
    }

    if ($action === 'refresh_commits') {
        $repository = 'MeTtHa-Matt/Warriors-Training-Club-App';
        $headers = "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github+json\r\n";
        $token = trim(dashboardSetting('GITHUB_TOKEN', dashboardSetting('GITHUB_API_TOKEN')));
        if ($token !== '') {
            $headers .= 'Authorization: Bearer ' . $token . "\r\n";
        }
        $context = stream_context_create(['http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 8, 'ignore_errors' => true]]);
        $raw = @file_get_contents('https://api.github.com/repos/' . $repository . '/commits?per_page=100', false, $context);
        $items = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($items) || isset($items['message'])) {
            dashboardJson(['error' => 'GitHub est momentanément indisponible.'], 502);
        }
        $pdo->beginTransaction();
        $upsert = $pdo->prepare('INSERT INTO dashboard_commits (sha, message, url, author, committed_at, repo) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE message = VALUES(message), url = VALUES(url), author = VALUES(author), committed_at = VALUES(committed_at), repo = VALUES(repo)');
        foreach ($items as $item) {
            $sha = (string) ($item['sha'] ?? '');
            if (!preg_match('/^[a-f0-9]{40}$/i', $sha)) {
                continue;
            }
            $date = (string) ($item['commit']['author']['date'] ?? '');
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                continue;
            }
            $upsert->execute([$sha, (string) ($item['commit']['message'] ?? ''), (string) ($item['html_url'] ?? ''), (string) ($item['commit']['author']['name'] ?? $item['author']['login'] ?? ''), gmdate('Y-m-d H:i:s', $timestamp), $repository]);
        }
        $pdo->exec('DELETE FROM dashboard_commits WHERE sha NOT IN (SELECT sha FROM (SELECT sha FROM dashboard_commits ORDER BY committed_at DESC LIMIT 200) AS recent_commits)');
        $pdo->commit();
        dashboardJson(['success' => true]);
    }

    if ($action === 'send_bulk_mail') {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message_html'] ?? ''));
        $signature = trim((string) ($_POST['signature'] ?? ''));
        $preset = (string) ($_POST['style_preset'] ?? 'classique');
        $styles = ['classique' => '#c7a545', 'chaleureux' => '#5e8c6a', 'professionnel' => '#4e778a', 'energetique' => '#bf5945'];
        if ($subject === '' || strlen($subject) > 180 || preg_match('/[\r\n]/', $subject) || $message === '' || strlen($message) > 20000 || $signature === '' || strlen($signature) > 120 || preg_match('/[\r\n]/', $signature)) {
            dashboardJson(['error' => 'Vérifiez l’objet, le message et la signature.'], 400);
        }
        if (!isset($styles[$preset])) {
            $preset = 'classique';
        }
        $attachments = [];
        $attachmentTotal = 0;
        $upload = $_FILES['attachments'] ?? null;
        if (is_array($upload) && isset($upload['name']) && is_array($upload['name'])) {
            if (count($upload['name']) > 5) {
                dashboardJson(['error' => 'Maximum cinq pièces jointes par envoi.'], 400);
            }
            foreach ($upload['name'] as $index => $originalName) {
                $uploadError = (int) ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $temporaryPath = (string) ($upload['tmp_name'][$index] ?? '');
                $size = (int) ($upload['size'][$index] ?? 0);
                if ($uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($temporaryPath) || $size < 1 || $size > 4 * 1024 * 1024) {
                    dashboardJson(['error' => 'Chaque pièce jointe doit être un fichier valide de 4 Mo maximum.'], 400);
                }
                $attachmentTotal += $size;
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', basename((string) $originalName)) ?: 'piece-jointe';
                $attachments[] = ['path' => $temporaryPath, 'name' => $safeName];
            }
        }
        if ($attachmentTotal > 12 * 1024 * 1024) {
            dashboardJson(['error' => 'Le poids total des pièces jointes dépasse 12 Mo.'], 400);
        }
        foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM', 'MAIL_FROM_NAME'] as $key) {
            if (dashboardSetting($key) === '') {
                dashboardJson(['error' => 'La configuration SMTP est incomplète.'], 503);
            }
        }
        if (!is_file(__DIR__ . '/vendor/autoload.php')) {
            dashboardJson(['error' => 'Le transport SMTP du dashboard n’est pas installé. Lancez composer install dans dashboard/.'], 503);
        }
        require_once __DIR__ . '/vendor/autoload.php';
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            dashboardJson(['error' => 'PHPMailer est absent des dépendances du dashboard.'], 503);
        }
        $recipients = $pdo->query("SELECT firstname, lastname, email FROM account_wtc WHERE ban = 0 AND accept_email = 1 AND email <> '' ORDER BY id")->fetchAll();
        if ($recipients === []) {
            dashboardJson(['error' => 'Aucun destinataire actif et inscrit aux e-mails.'], 409);
        }
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeSignature = htmlspecialchars($signature, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $accent = $styles[$preset];
        $purifierConfig = \HTMLPurifier_Config::createDefault();
        $purifierConfig->set('HTML.Allowed', 'p,br,strong,b,em,i,u,ul,ol,li,blockquote,a[href|title],h2,h3');
        $purifierConfig->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $purifierCache = DASHBOARD_STORAGE . '/htmlpurifier';
        if (!is_dir($purifierCache)) {
            @mkdir($purifierCache, 0700, true);
        }
        $purifierConfig->set('Cache.SerializerPath', $purifierCache);
        $safeMessage = (new \HTMLPurifier($purifierConfig))->purify($message);
        if (trim(strip_tags($safeMessage)) === '') {
            dashboardJson(['error' => 'Rédige un message avant l’envoi.'], 400);
        }
        $body = '<!doctype html><html lang="fr"><meta charset="utf-8"><body style="margin:0;background:#f1f4ef;color:#14211d;font-family:Arial,sans-serif"><main style="max-width:640px;margin:24px auto;padding:28px;background:#fff;border-top:5px solid ' . $accent . '"><h1 style="font-size:22px">' . $safeSubject . '</h1><div style="font-size:15px;line-height:1.7">' . $safeMessage . '</div><p style="margin-top:24px">À très vite,<br><strong>' . $safeSignature . '</strong></p></main></body></html>';
        $sent = 0;
        foreach ($recipients as $recipient) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = dashboardSetting('MAIL_HOST');
                $mail->Port = max(1, (int) dashboardSetting('MAIL_PORT', '587'));
                $mail->SMTPAuth = true;
                $mail->Username = dashboardSetting('MAIL_USERNAME');
                $mail->Password = dashboardSetting('MAIL_PASSWORD');
                $encryption = strtolower(dashboardSetting('MAIL_ENCRYPTION', 'tls'));
                $mail->SMTPSecure = $encryption === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(dashboardSetting('MAIL_FROM'), dashboardSetting('MAIL_FROM_NAME'));
                $mail->addAddress($recipient['email'], trim($recipient['firstname'] . ' ' . $recipient['lastname']));
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $plainMessage = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $safeMessage)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $mail->AltBody = $plainMessage . "\n\n" . $signature;
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }
                $mail->send();
                $sent++;
            } catch (Throwable $error) {
                error_log('[dashboard-admin-mail] ' . $error->getMessage());
            }
        }
        dashboardJson(['success' => $sent > 0, 'sent' => $sent, 'failed' => count($recipients) - $sent], $sent > 0 ? 200 : 502);
    }

    dashboardJson(['error' => 'Action inconnue.'], 400);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    dashboardAdminRespondError($error);
}