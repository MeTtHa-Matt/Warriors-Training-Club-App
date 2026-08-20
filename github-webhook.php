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

http_response_code(200);
echo 'OK';
