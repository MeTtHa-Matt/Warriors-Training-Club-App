<?php
// Return commit details (files changed) from GitHub API for a given sha
header('Content-Type: application/json; charset=utf-8');

$sha = $_GET['sha'] ?? '';
if ($sha === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing sha']);
    exit;
}

$repo = 'MeTtHa-Matt/Warriors-Training-Club-App';
$apiUrl = "https://api.github.com/repos/" . $repo . "/commits/" . rawurlencode($sha);
// allow authenticated requests via GITHUB_TOKEN to avoid rate limits
$token = trim(getenv('GITHUB_TOKEN') ?: getenv('GITHUB_API_TOKEN') ?: '');
$headers = "User-Agent: Warriors-Training-Club-App\r\nAccept: application/vnd.github.v3+json\r\n";
if ($token !== '') {
    $headers .= "Authorization: token " . $token . "\r\n";
}
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => $headers,
        'timeout' => 8,
    ],
];
$context = stream_context_create($opts);
$raw = @file_get_contents($apiUrl, false, $context);
if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to fetch from GitHub']);
    exit;
}
if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to fetch from GitHub']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from GitHub']);
    exit;
}

$out = [
    'sha' => $data['sha'] ?? $sha,
    'message' => $data['commit']['message'] ?? '',
    'author' => $data['commit']['author']['name'] ?? '',
    'date' => $data['commit']['author']['date'] ?? '',
    'html_url' => $data['html_url'] ?? '',
    'files' => $data['files'] ?? [],
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
