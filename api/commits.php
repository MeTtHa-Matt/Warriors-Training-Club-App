<?php
// Simple API endpoint returning stored commits as JSON
header('Content-Type: application/json; charset=utf-8');

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

// If no stored commits, try fetching from GitHub API (public repo)
if (empty($commits)) {
    $repo = 'MeTtHa-Matt/Warriors-Training-Club-App';
    $apiUrl = "https://api.github.com/repos/" . $repo . "/commits?per_page=100";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github.v3+json\r\n",
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
                // attempt to write cache
                @file_put_contents(__DIR__ . '/../data/commits.json', json_encode(array_reverse($fetched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                $commits = array_reverse($fetched);
            }
        }
    }
}

echo json_encode(array_values($commits));
exit;
