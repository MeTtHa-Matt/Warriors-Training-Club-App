<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

function getMailerSettings(): array
{
    $host = getenv('MAIL_HOST');
    $port = (int) (getenv('MAIL_PORT'));
    $username = getenv('MAIL_USERNAME');
    $from = getenv('MAIL_FROM');
    $fromName = getenv('MAIL_FROM_NAME');
    $encryption = strtolower(getenv('MAIL_ENCRYPTION'));
    $secure = $encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;

    return [
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'from' => $from,
        'fromName' => $fromName,
        'secure' => $secure,
        'encryption' => $encryption,
    ];
}

function testMailerConfiguration(): array
{
    $issues = [];
    $settings = getMailerSettings();

    if (empty(getenv('MAIL_PASSWORD'))) {
        $issues[] = 'MAIL_PASSWORD absent ou vide';
    }

    if (!checkdnsrr($settings['host'], 'MX') && !checkdnsrr($settings['host'], 'A')) {
        $issues[] = "Impossible de résoudre le host SMTP ({$settings['host']})";
    }

    $scheme = $settings['secure'] === PHPMailer::ENCRYPTION_SMTPS ? 'ssl' : 'tcp';
    $connection = @stream_socket_client("{$scheme}://{$settings['host']}:{$settings['port']}", $errno, $errstr, 5);
    if ($connection === false) {
        $issues[] = "Impossible de se connecter au SMTP {$settings['host']}:{$settings['port']} - {$errno} {$errstr}";
    } else {
        fclose($connection);
    }

    return $issues;
}

function getApplicationBaseUrl(): string
{
    $envBaseUrl = getenv('APP_BASE_URL');
    if (!empty($envBaseUrl)) {
        return rtrim($envBaseUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    if (stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false || stripos($host, '::1') !== false) {
        return $scheme . '://' . $host . '/WTC-App';
    }

    return $scheme . '://' . $host;
}

function sendVerificationEmail($email, $firstname, $token)
{
    $mail = new PHPMailer(true);
    $smtpDebug = [];
    $settings = getMailerSettings();
    $diagnostics = testMailerConfiguration();

    if (!empty($diagnostics)) {
        error_log('Diagnostics mailer : ' . implode(' | ', $diagnostics));
    }

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) use (&$smtpDebug) {
            $smtpDebug[] = trim($str);
        };
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = $settings['secure'];
        $mail->Port = $settings['port'];

        $mail->setFrom($settings['from'], $settings['fromName']);
        $mail->addAddress($email, $firstname);
        $mail->addReplyTo($settings['from'], $settings['fromName']);

        $mail->isHTML(true);
        $mail->Subject = 'Confirmez votre adresse email - Warriors Training Club';

        $link = getApplicationBaseUrl() . '/verify.php?token=' . urlencode($token);

        $mail->Body = buildVerificationEmailHtml($firstname, $link);
        $mail->AltBody = "Bonjour $firstname,\n\nConfirmez votre inscription au Warriors Training Club en ouvrant ce lien :\n$link\n\nCe lien expire dans 24h.";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        $details = trim(implode(' | ', $smtpDebug));
        if ($details !== '') {
            $error .= ' | debug: ' . $details;
        }

        error_log('Erreur envoi mail vérification : ' . $error);
        return ['success' => false, 'error' => $error];
    }
}

function sendPasswordResetEmail(string $email, string $firstname, string $link): array
{
    $mail = new PHPMailer(true);
    $smtpDebug = [];
    $settings = getMailerSettings();
    $diagnostics = testMailerConfiguration();

    if (!empty($diagnostics)) {
        error_log('Diagnostics mailer (reset password) : ' . implode(' | ', $diagnostics));
    }

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) use (&$smtpDebug) {
            $smtpDebug[] = trim($str);
        };
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = $settings['secure'];
        $mail->Port = $settings['port'];

        $mail->setFrom($settings['from'], $settings['fromName']);
        $mail->addAddress($email, $firstname);
        $mail->addReplyTo($settings['from'], $settings['fromName']);

        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de ton mot de passe - Warriors Training Club';

        $safeFirstname = htmlspecialchars($firstname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background-color:#0f0f0f; font-family:Arial, sans-serif; color:#f4efe2;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f0f0f; padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:linear-gradient(180deg, #171717, #0c0b0a); border:1px solid #3b3425; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#000000; padding:28px; text-align:center;">
                            <h1 style="color:#f2d46f; margin:0; font-size:22px; letter-spacing:1px;">WARRIORS TRAINING CLUB</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;">
                            <h2 style="color:#f4efe2; font-size:20px; margin-top:0;">Bonjour {$safeFirstname},</h2>
                            <p style="color:#d9d1bf; font-size:15px; line-height:1.7;">
                                Tu as demandé à réinitialiser ton mot de passe sur l’espace membre du Warriors Training Club.
                                Clique sur le bouton ci-dessous pour choisir un nouveau mot de passe.
                            </p>
                            <div style="text-align:center; margin:32px 0;">
                                <a href="{$safeLink}" style="background:linear-gradient(90deg, #d5a62c, #a87314); color:#0c0b0a; text-decoration:none; padding:14px 32px; border-radius:999px; font-weight:bold; font-size:15px; display:inline-block;">
                                    Réinitialiser mon mot de passe
                                </a>
                            </div>
                            <p style="color:#9a917d; font-size:13px; line-height:1.5;">
                                Si tu n’es pas à l’origine de cette demande, tu peux ignorer cet email. Le lien expire dans 1 heure.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#171717; padding:18px; text-align:center;">
                            <p style="color:#8b8574; font-size:12px; margin:0;">Warriors Training Club · Ne répondez pas à cet email</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        $mail->AltBody = "Bonjour {$firstname},\n\nTu as demandé à réinitialiser ton mot de passe du Warriors Training Club. Ouvre ce lien :\n{$link}\n\nSi tu n'es pas à l'origine de cette demande, ignore cet email. Le lien expire dans 1 heure.";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        $details = trim(implode(' | ', $smtpDebug));
        if ($details !== '') {
            $error .= ' | debug: ' . $details;
        }

        error_log('Erreur envoi mail reset password : ' . $error);
        return ['success' => false, 'error' => $error];
    }
}

function sendReportNotificationEmail(string $subject, string $message, string $userName, string $userEmail, ?string $ipAddress = null, ?string $userAgent = null): array
{
    $mail = new PHPMailer(true);
    $smtpDebug = [];
    $settings = getMailerSettings();
    $diagnostics = testMailerConfiguration();

    if (!empty($diagnostics)) {
        error_log('Diagnostics mailer (signalement) : ' . implode(' | ', $diagnostics));
    }

    $recipient = getenv('REPORT_RECIPIENT') ?: getenv('MAIL_TO') ?: $settings['from'];

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) use (&$smtpDebug) {
            $smtpDebug[] = trim($str);
        };
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = $settings['secure'];
        $mail->Port = $settings['port'];

        $mail->setFrom($settings['from'], $settings['fromName']);
        $mail->addAddress($recipient);
        $mail->addReplyTo($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = 'Nouveau signalement WTC : ' . $subject;

        $safeUserName = htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $safeEmail = htmlspecialchars($userEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeIp = htmlspecialchars((string) ($ipAddress ?? 'Inconnu'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeAgent = htmlspecialchars((string) ($userAgent ?? 'Inconnu'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial, sans-serif; color:#111827;">
    <h2 style="color:#b30000;">Nouveau signalement reçu</h2>
    <p><strong>Sujet :</strong> {$safeSubject}</p>
    <p><strong>Envoyé par :</strong> {$safeUserName} ({$safeEmail})</p>
    <p><strong>IP :</strong> {$safeIp}</p>
    <p><strong>Agent utilisateur :</strong> {$safeAgent}</p>
    <hr>
    <div>{$safeMessage}</div>
</body>
</html>
HTML;
        $mail->AltBody = "Nouveau signalement reçu\n\nSujet : {$subject}\nEnvoyé par : {$userName} ({$userEmail})\nIP : {$ipAddress}\nAgent utilisateur : {$userAgent}\n\nMessage :\n{$message}";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        $details = trim(implode(' | ', $smtpDebug));
        if ($details !== '') {
            $error .= ' | debug: ' . $details;
        }

        error_log('Erreur envoi mail signalement : ' . $error);
        return ['success' => false, 'error' => $error];
    }
}

function sendBulkHtmlEmail(array $recipients, string $subject, string $htmlBody, array $embeddedImages = [], array $attachments = [])
{
    $mail = new PHPMailer(true);
    $smtpDebug = [];
    $settings = getMailerSettings();
    $diagnostics = testMailerConfiguration();

    if (!empty($diagnostics)) {
        error_log('Diagnostics mailer (newsletter) : ' . implode(' | ', $diagnostics));
    }

    if (empty($recipients)) {
        return ['success' => false, 'error' => 'Aucun destinataire disponible.'];
    }

    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) use (&$smtpDebug) {
            $smtpDebug[] = trim($str);
        };
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = $settings['secure'];
        $mail->Port = $settings['port'];

        $mail->setFrom($settings['from'], $settings['fromName']);
        $mail->addReplyTo($settings['from'], $settings['fromName']);

        foreach ($recipients as $recipient) {
            $email = $recipient['email'] ?? null;
            if (empty($email)) {
                continue;
            }
            $name = $recipient['name'] ?? '';
            $mail->addBCC($email, $name);
        }

        if (empty($mail->getToAddresses()) && empty($mail->getCcAddresses()) && empty($mail->getBccAddresses())) {
            return ['success' => false, 'error' => 'Aucune adresse email valide trouvée.'];
        }

        foreach ($embeddedImages as $index => $image) {
            $cid = $image['cid'] ?? 'inline-image-' . $index;
            $mail->addStringEmbeddedImage(
                $image['content'],
                $cid,
                $image['filename'] ?? 'image_' . $index . '.png',
                'base64',
                $image['mime'] ?? 'application/octet-stream'
            );
        }

        foreach ($attachments as $attachmentPath) {
            if (is_string($attachmentPath) && is_file($attachmentPath)) {
                $mail->addAttachment($attachmentPath);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        $details = trim(implode(' | ', $smtpDebug));
        if ($details !== '') {
            $error .= ' | debug: ' . $details;
        }

        error_log('Erreur envoi mail groupe : ' . $error);
        return ['success' => false, 'error' => $error];
    }
}

function buildVerificationEmailHtml($firstname, $link)
{
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:'Segoe UI', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:30px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background-color:#000000; padding:28px; text-align:center;">
                            <h1 style="color:#ffffff; margin:0; font-size:22px; letter-spacing:1px;">WARRIORS TRAINING CLUB</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;">
                            <h2 style="color:#1a1a1a; font-size:20px; margin-top:0;">Bonjour {$firstname},</h2>
                            <p style="color:#444444; font-size:15px; line-height:1.6;">
                                Merci de vous être inscrit(e) sur l'espace membre du Warriors Training Club.
                                Pour activer votre compte, confirmez votre adresse email en cliquant sur le bouton ci-dessous.
                            </p>
                            <div style="text-align:center; margin:32px 0;">
                                <a href="{$link}" style="background-color:#b30000; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:bold; font-size:15px; display:inline-block;">
                                    Confirmer mon email
                                </a>
                            </div>
                            <p style="color:#888888; font-size:13px; line-height:1.5;">
                                Ce lien est valable 24 heures. Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement cet email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f4f4f5; padding:18px; text-align:center;">
                            <p style="color:#999999; font-size:12px; margin:0;">Warriors Training Club · Ne répondez pas à cet email</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}