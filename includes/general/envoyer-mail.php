<?php
if (empty($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$currentId = (int) $_SESSION['user_id'];
$adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
$adminCheckStmt->execute([$currentId]);
$isAdmin = (bool) $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = null;
$recipientsStmt = $pdo->prepare('SELECT id, firstname, lastname, email FROM account_wtc WHERE ban = 0 AND accept_email = 1 AND email <> "" ORDER BY lastname ASC, firstname ASC');
$recipientsStmt->execute();
$recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
$recipientCount = count($recipients);

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $sentCount = (int) ($_GET['count'] ?? $recipientCount ?? 0);
    $success = 'Mail envoyé à ' . $sentCount . ' utilisateur' . ($sentCount > 1 ? 's' : '') . '.';
}

$stylePresets = [
    'classique' => [
        'label' => 'Classique',
        'accent' => '#c9a227',
        'hero' => 'Un message simple et élégant',
    ],
    'chaleureux' => [
        'label' => 'Chaleureux',
        'accent' => '#5e8c6a',
        'hero' => 'Une voix plus proche des membres',
    ],
    'professionnel' => [
        'label' => 'Professionnel',
        'accent' => '#2f5d7c',
        'hero' => 'Un ton structuré et rassurant',
    ],
    'energetique' => [
        'label' => 'Énergique',
        'accent' => '#b34d3f',
        'hero' => 'Un message dynamique et motivant',
    ],
];

$postedSubject = '';
$postedSignature = 'L’équipe du club';
$postedStylePreset = 'classique';
$postedMessageHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSubject = trim($_POST['subject'] ?? '');
    $postedSignature = trim($_POST['signature'] ?? 'L’équipe du club');
    $postedStylePreset = $_POST['style_preset'] ?? 'classique';
    $postedStylePreset = array_key_exists($postedStylePreset, $stylePresets) ? $postedStylePreset : 'classique';
    $postedMessageHtml = trim($_POST['message_html'] ?? '');
    $subject = $postedSubject;
    $signature = $postedSignature;
    $stylePreset = $postedStylePreset;

    if ($subject === '') {
        $errors[] = 'Ajoute un objet pour ce mail.';
    }
    if ($postedMessageHtml === '') {
        $errors[] = 'Rédige un message avant l’envoi.';
    }
    if ($recipientCount === 0) {
        $errors[] = 'Aucun destinataire actif à qui envoyer le mail.';
    }

    if (empty($errors)) {
        $uploadDir = __DIR__ . '/uploads/mail-attachments';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $index => $name) {
                $tmpName = $_FILES['attachments']['tmp_name'][$index] ?? null;
                if (!is_uploaded_file($tmpName)) {
                    continue;
                }
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($name));
                $targetPath = $uploadDir . '/' . uniqid('mail_', true) . '-' . $safeName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $attachments[] = $targetPath;
                }
            }
        }

        $htmlBody = buildMailHtmlBody($subject, $postedMessageHtml, $signature);

        $result = sendBulkHtmlEmail(
            array_map(function ($user) {
                return [
                    'email' => $user['email'],
                    'name' => trim($user['firstname'] . ' ' . $user['lastname']),
                ];
            }, $recipients),
            $subject,
            $htmlBody,
            [],
            $attachments
        );

        foreach ($attachments as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        if (!empty($result['success'])) {
            header('Location: envoyer-mail.php?sent=1&count=' . $recipientCount);
            exit;
        } else {
            $errors[] = $result['error'] ?? 'Impossible d’envoyer le mail.';
        }
    }
}

$pageTitle = 'Warriors Training Club - Envoyer un mail';

function buildMailHtmlBody(string $subject, string $messageHtml, string $signature): string
{
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeSignature = htmlspecialchars($signature, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = $messageHtml;

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:'Segoe UI', Arial, sans-serif; color:#1f1f1f;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" style="max-width:640px; margin:0 auto; background-color:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">
          <tr>
            <td style="padding:24px 24px 18px;">
              <h1 style="margin:0 0 12px; font-size:24px; color:#111827;">{$safeSubject}</h1>
              <div style="font-size:16px; line-height:1.7; color:#1f2937;">{$safeMessage}</div>
              <p style="margin:20px 0 0; font-size:15px; line-height:1.6; color:#4b5563;">À très vite,<br><strong>{$safeSignature}</strong></p>
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