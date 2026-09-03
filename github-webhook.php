<?php
// Endpoint to receive GitHub webhooks (push events)
// Public URL: https://your-domain/github-webhook.php

// Read raw payload
$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    echo 'No payload';
    exit;
}

// Note: signature validation removed — webhook accepts push events without HMAC check

$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event !== 'push') {
    // Only handle push events for now
    http_response_code(200);
    echo 'Event ignored';
    exit;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

$commits = $data['commits'] ?? [];
$repoName = $data['repository']['full_name'] ?? '';

function indexNowUrls(array $commits): array
{
    $baseUrl = rtrim((string) (getenv('INDEXNOW_SITE_URL') ?: getenv('APP_BASE_URL') ?: 'https://warriors-training-club.judo-club-mormant.fr'), '/');
    $sitemapPath = __DIR__ . '/sitemap.xml';
    $sitemapUrls = [];
    $sharedChange = false;
    $changedFiles = [];

    foreach ($commits as $commit) {
        foreach (['added', 'modified', 'removed'] as $changeType) {
            foreach (($commit[$changeType] ?? []) as $file) {
                if (!is_string($file)) {
                    continue;
                }

                $changedFiles[] = [$file, $changeType];
                if (preg_match('#^(?:includes/|css/|js/|manifest\.json$|sw\.js$)#', $file)) {
                    $sharedChange = true;
                }
            }
        }
    }

    if (is_readable($sitemapPath)) {
        $xml = @simplexml_load_file($sitemapPath);
        if ($xml !== false) {
            foreach ($xml->url as $entry) {
                $url = trim((string) $entry->loc);
                if ($url !== '') {
                    $sitemapUrls[] = $url;
                }
            }
        }
    }

    if ($sharedChange) {
        return array_values(array_unique($sitemapUrls));
    }

    $urls = [];
    foreach ($changedFiles as [$file, $changeType]) {
        if (!preg_match('#^([a-zA-Z0-9_-]+\.php)$#', $file, $matches)) {
            continue;
        }

        $urls[] = $baseUrl . '/' . $matches[1];
    }

    return array_values(array_unique($urls));
}

function notifyIndexNow(array $urls): void
{
    if ($urls === []) {
        return;
    }

    $command = [PHP_BINARY, __DIR__ . '/indexnow.php', ...$urls];
    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        error_log('Impossible de lancer indexnow.php après le push GitHub.');
        return;
    }

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        error_log('Échec IndexNow après le push GitHub : ' . trim($error ?: $output));
    }
}

$path = __DIR__ . '/data/commits.json';
$existing = [];
if (is_file($path)) {
    $json = @file_get_contents($path);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
}

// Normalize and append new commits if not present
foreach ($commits as $c) {
    $id = $c['id'] ?? ($c['sha'] ?? '');
    if ($id === '') {
        continue;
    }

    $entry = [
        'id' => $id,
        'message' => $c['message'] ?? '',
        'url' => $c['url'] ?? ($c['html_url'] ?? ''),
        'author' => $c['author']['name'] ?? $c['author']['username'] ?? '',
        'timestamp' => $c['timestamp'] ?? date('c'),
        'repo' => $repoName,
    ];

    $exists = false;
    foreach ($existing as $ex) {
        if (isset($ex['id']) && $ex['id'] === $entry['id']) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $existing[] = $entry;
    }
}

// Keep last 200 commits
if (count($existing) > 200) {
    $existing = array_slice($existing, -200);
}

// Store safely
@file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

notifyIndexNow(indexNowUrls($commits));

http_response_code(200);
echo 'OK';
