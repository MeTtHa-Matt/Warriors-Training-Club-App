<?php
// Simple API endpoint returning stored commits as JSON
header('Content-Type: application/json; charset=utf-8');
// prevent caching by browsers/proxies
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$path = __DIR__ . '/../data/commits.json';
$commits = [];
if (is_file($path)) {
    $json = @file_get_contents($path);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            // return most recent first
            $commits = array_reverse($decoded);
        }
    }
}

// If client requested a force refresh or no stored commits, fetch from GitHub API
if (isset($_GET['force']) || empty($commits)) {
    $repo = 'MeTtHa-Matt/Warriors-Training-Club-App';
    $apiUrl = "https://api.github.com/repos/" . $repo . "/commits?per_page=100";
    $token = trim(getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_TOKEN') ?: '');
    $headers = "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github.v3+json\r\n";
    if ($token !== '') {
        $headers .= "Authorization: token " . $token . "\r\n";
    }
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => $headers,
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents($apiUrl, false, $context);
    if ($raw !== false) {
        $items = json_decode($raw, true);
        if (is_array($items)) {
            $fetched = [];
            foreach ($items as $it) {
                $id = $it['sha'] ?? '';
                $fetched[] = [
                    'id' => $id,
                    'message' => $it['commit']['message'] ?? '',
                    'url' => $it['html_url'] ?? '',
                    'author' => $it['commit']['author']['name'] ?? ($it['author']['login'] ?? ''),
                    'timestamp' => $it['commit']['author']['date'] ?? '',
                    'repo' => $repo,
                ];
            }
            if (!empty($fetched)) {
                // store cache in file as oldest-first, but keep $commits as newest-first
                @file_put_contents(__DIR__ . '/../data/commits.json', json_encode(array_reverse($fetched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                $commits = $fetched; // fetched is newest-first from GitHub API
            }
        } else {
            // if GitHub returned an error object, surface it to callers
            $decodedErr = json_decode($raw, true);
            if (is_array($decodedErr) && isset($decodedErr['message'])) {
                http_response_code(502);
                echo json_encode(['error' => 'GitHub API error: ' . ($decodedErr['message'] ?? 'unknown')]);
                exit;
            }
        }
    }
}

echo json_encode(array_values($commits));
exit;
