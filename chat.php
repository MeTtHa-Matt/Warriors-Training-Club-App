<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->load();
}

function buildSiteContext(PDO $pdo): array
{
    $context = [];

    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT firstname, lastname, admin, gerer_seances FROM account_wtc WHERE id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $context['user'] = [
                'name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
                'admin' => (int) ($user['admin'] ?? 0),
                'gerer_seances' => (int) ($user['gerer_seances'] ?? 0),
            ];
        }
    }

    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM account_wtc WHERE ban = 0');
    $context['users_count'] = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM seances WHERE date_seance >= CURDATE()');
    $context['upcoming_sessions_count'] = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM inscriptions_seances');
    $context['inscriptions_count'] = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->query('SELECT id, date_seance, heure_debut, heure_fin, type_seance, coach, lieu_seance, lieu_rdv FROM seances WHERE date_seance >= CURDATE() ORDER BY date_seance ASC, heure_debut ASC LIMIT 5');
    $context['upcoming_sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query('SELECT label, title, url FROM index_links ORDER BY display_order ASC, id ASC LIMIT 10');
    $context['index_links'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $context;
}

function formatSiteContext(array $context): string
{
    $lines = ['Contexte du site Warriors Training Club (données disponibles dans la base) :'];

    if (!empty($context['user']['name'])) {
        $lines[] = '- Utilisateur connecté : ' . $context['user']['name'];
        $lines[] = '- Rôle : ' . (($context['user']['admin'] ?? 0) ? 'administrateur' : 'membre');
    }

    $lines[] = '- Nombre d’utilisateurs actifs : ' . ($context['users_count'] ?? 0);
    $lines[] = '- Nombre de séances à venir : ' . ($context['upcoming_sessions_count'] ?? 0);
    $lines[] = '- Nombre d’inscriptions enregistrées : ' . ($context['inscriptions_count'] ?? 0);

    if (!empty($context['upcoming_sessions'])) {
        $lines[] = '- Prochaines séances :';
        foreach ($context['upcoming_sessions'] as $session) {
            $date = $session['date_seance'] ?? '';
            $start = $session['heure_debut'] ?? '';
            $end = $session['heure_fin'] ?? '';
            $type = $session['type_seance'] ?? 'Séance';
            $coach = $session['coach'] ?? '';
            $lines[] = '  • ' . $date . ' ' . $start . '-' . $end . ' : ' . $type . ' avec ' . $coach;
        }
    }

    if (!empty($context['index_links'])) {
        $lines[] = '- Liens rapides disponibles sur la page d’accueil :';
        foreach ($context['index_links'] as $link) {
            $label = $link['label'] ?? '';
            $title = $link['title'] ?? '';
            $url = $link['url'] ?? '';
            $lines[] = '  • ' . $label . ' - ' . $title . ' (' . $url . ')';
        }
    }

    return implode("\n", $lines);
}

function callGroqApi(string $apiKey, string $systemPrompt, string $userMessage): array
{
    $payload = [
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ],
        'temperature' => 0.7,
        'max_tokens' => 500,
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $response === '') {
            return ['success' => false, 'message' => 'Erreur CURL : ' . ($error ?: 'aucune réponse') . ' (HTTP ' . $httpCode . ')'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => ''];
        }

        if (!empty($decoded['error']['message'])) {
            return ['success' => false, 'message' => $decoded['error']['message']];
        }

        $answer = $decoded['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'message' => trim((string) $answer)];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $response = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $context);
    if ($response === false || $response === '') {
        return ['success' => false, 'message' => ''];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => ''];
    }

    if (!empty($decoded['error']['message'])) {
        return ['success' => false, 'message' => $decoded['error']['message']];
    }

    $answer = $decoded['choices'][0]['message']['content'] ?? '';
    return ['success' => true, 'message' => trim((string) $answer)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $message = isset($data['message']) ? trim((string) $data['message']) : '';

    if ($message === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'answer' => 'Le message est vide.']);
        exit;
    }

    $siteContext = buildSiteContext($pdo);
    $contextText = formatSiteContext($siteContext);

    $systemPrompt = "Tu es l'assistant chaleureux et utile du site Warriors Training Club. Réponds en français, de façon naturelle, conviviale et concise. Utilise uniquement les informations du contexte fourni et de la base de données. Si l'information demandée n'est pas disponible, dis-le clairement sans inventer. Adopte un ton amical, motivant et proche de l'esprit du club, comme un coach qui aide à trouver rapidement la bonne information.";
    $userPrompt = "Question de l'utilisateur : {$message}\n\nContexte disponible :\n{$contextText}";

    $apiKey = getenv('IA_API_KEY') ?: '';
    $answer = '';

    if ($apiKey !== '') {
        $result = callGroqApi($apiKey, $systemPrompt, $userPrompt);
        if (!empty($result['message'])) {
            $answer = $result['message'];
        } else {
            $answer = 'Erreur d’IA : ' . ($result['message'] ?? 'réponse vide');
        }
    } else {
        $answer = 'La clé IA_API_KEY n’est pas configurée.';
    }

    if ($answer === '') {
        $answer = 'Je n’ai pas pu récupérer une réponse en ce moment. Vérifie la clé IA_API_KEY, l’accès réseau ou la configuration SSL de votre environnement local.';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'answer' => $answer,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Warriors Training Club - Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607051">
    <link rel="stylesheet" href="css/chat.css?v=202607051">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" sizes="any" href="./img/wtc.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./img/wtc.png">
    <meta name="application-name" content="Warriors Training Club">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Warriors">
    <meta name="theme-color" content="#0C0B0A">
    <meta name="mobile-web-app-capable" content="yes">
</head>

<body class="chat-page">

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <span class="hero-badge mb-3"><span class="dot"></span>Bêta</span>
            <h1 class="mt-3 mb-2">L'assistant <span class="accent">Warriors</span></h1>
            <p class="lead mb-0">
                Pose tes questions sur les séances, le règlement ou ton adhésion. Réponses instantanées,
                dispo à toute heure.
            </p>
        </div>
    </section>

    <section class="section section--compact chat-section">
        <div class="container">
            <div class="chat-shell" id="chatShell">

                <div class="chat-shell__head">
                    <div class="chat-shell__coach">
                        <span class="chat-shell__coach-avatar"><i class="bi bi-stars"></i></span>
                        <div>
                            <p class="chat-shell__coach-name">Assistant WTC</p>
                            <p class="chat-shell__coach-status"><span class="chat-shell__dot"></span>En ligne</p>
                        </div>
                    </div>
                    <button type="button" class="chat-shell__reset" id="chatReset" title="Nouvelle conversation">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>

                <div class="chat-shell__body" id="chatMessages">
                    <div class="chat-empty" id="chatEmpty">
                        <span class="chat-empty__icon"><i class="bi bi-chat-dots"></i></span>
                        <p class="chat-empty__title">Aucun message pour l'instant</p>
                        <p class="chat-empty__text">Écris ta première question ci-dessous pour démarrer la
                            conversation.</p>
                        <div class="chat-suggestions">
                            <button type="button" class="chat-suggestion">Horaires de la semaine</button>
                            <button type="button" class="chat-suggestion">Comment m'inscrire ?</button>
                            <button type="button" class="chat-suggestion">Règlement intérieur</button>
                        </div>
                    </div>
                </div>

                <form class="chat-shell__composer" id="chatForm" autocomplete="off">
                    <textarea class="chat-input" id="chatInput" rows="1" placeholder="Écris ton message..."
                        maxlength="2000"></textarea>
                    <button type="submit" class="chat-send" id="chatSend" disabled aria-label="Envoyer">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>

            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chat.js?v=202607051"></script>

</body>

</html>