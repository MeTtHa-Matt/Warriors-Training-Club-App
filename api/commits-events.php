<?php
// SSE endpoint that notifies clients when `data/commits.json` changes
set_time_limit(0);
ignore_user_abort(true);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$path = __DIR__ . '/../data/commits.json';
$lastMTime = 0;
$lastSha = null;

// Initialize lastSha from file if present
if (is_file($path)) {
    $raw = @file_get_contents($path);
    if ($raw !== false) {
        $arr = json_decode($raw, true);
        if (is_array($arr) && !empty($arr)) {
            $lastSha = $arr[0]['id'] ?? $arr[0]['sha'] ?? null;
        }
    }
}

// Send a comment to establish the connection
echo ": connected\n\n";
@ob_flush(); @flush();

$repo = 'MeTtHa-Matt/Warriors-Training-Club-App';
$apiLatestUrl = "https://api.github.com/repos/" . $repo . "/commits?per_page=1";
$contextOpts = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github.v3+json\r\n",
        'timeout' => 8,
    ],
];
$context = stream_context_create($contextOpts);

while (!connection_aborted()) {
    // First check GitHub for the latest commit
    $rawLatest = @file_get_contents($apiLatestUrl, false, $context);
    $gotNew = false;
    if ($rawLatest !== false) {
        $items = json_decode($rawLatest, true);
        if (is_array($items) && isset($items[0]['sha'])) {
            $sha = $items[0]['sha'];
            if ($lastSha === null) {
                $lastSha = $sha;
            } elseif ($sha !== $lastSha) {
                // new commit detected: fetch recent commits and update cache
                $lastSha = $sha;
                $apiAll = "https://api.github.com/repos/" . $repo . "/commits?per_page=100";
                $rawAll = @file_get_contents($apiAll, false, $context);
                if ($rawAll !== false) {
                    $itemsAll = json_decode($rawAll, true);
                    if (is_array($itemsAll)) {
                        $fetched = [];
                        foreach ($itemsAll as $it) {
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
                            @file_put_contents($path, json_encode(array_reverse($fetched), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                            $gotNew = true;
                        }
                    }
                }
            }
        }
    }

    // If file changed or we fetched new data, send it
    clearstatcache(false, $path);
    $mtime = is_file($path) ? filemtime($path) : 0;
    if ($mtime > $lastMTime || $gotNew) {
        $lastMTime = $mtime;
        $payload = @file_get_contents($path);
        if ($payload === false) {
            $payload = json_encode([]);
        }
        $data = trim($payload);
        echo "data: " . $data . "\n\n";
        @ob_flush(); @flush();
    }

    // sleep before next poll
    sleep(10);
}
