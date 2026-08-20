<?php
// SSE endpoint that notifies clients when `data/commits.json` changes
set_time_limit(0);
ignore_user_abort(true);
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$path = __DIR__ . '/../data/commits.json';
$lastMTime = 0;

// Send a comment to establish the connection
echo ": connected\n\n";
@ob_flush(); @flush();

while (!connection_aborted()) {
    clearstatcache(false, $path);
    $mtime = is_file($path) ? filemtime($path) : 0;
    if ($mtime > $lastMTime) {
        $lastMTime = $mtime;
        $payload = @file_get_contents($path);
        if ($payload === false) {
            $payload = json_encode([]);
        }
        // send as a single event
        $lines = explode("\n", trim($payload));
        // compact into one-line JSON for safe transport
        $data = trim($payload);
        echo "data: " . $data . "\n\n";
        @ob_flush(); @flush();
    }
    // sleep a bit
    usleep(500000); // 500ms
}
