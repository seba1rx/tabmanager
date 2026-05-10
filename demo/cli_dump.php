<?php
/**
 * Dumps all TabManager session data to stdout as pretty-printed JSON.
 *
 * Reads from the /tabmanager/debug_js endpoint (runs inside the web process
 * which has real session access). The session save directory is restricted
 * to write-only for security, so filesystem reads are not reliable from CLI.
 *
 * Usage:
 *   php cli_dump.php [host]
 *
 * Examples:
 *   php cli_dump.php
 *   php cli_dump.php http://localhost:8080
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

$host = rtrim($argv[1] ?? 'http://localhost', '/');
$url  = "$host/tabmanager/debug_js";

fwrite(STDERR, "Connecting to: $url\n");
fwrite(STDERR, "  (pass a different host as first argument, e.g. php cli_dump.php http://localhost:8080)\n\n");

$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'timeout'         => 5,
        // Spoof REMOTE_ADDR is not possible via HTTP; the debug endpoint allows
        // localhost (127.0.0.1) so this only works when the server is local.
        'ignore_errors'   => true,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    fwrite(STDERR, "Could not reach $url\n");
    fwrite(STDERR, "Make sure the PHP server is running (e.g. php -S localhost:80 from demo/)\n");
    exit(1);
}

$status = 200;
if (isset($http_response_header)) {
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0] ?? '', $m);
    $status = (int) ($m[1] ?? 200);
}

if ($status === 403) {
    fwrite(STDERR, "Access denied (403). Server must be on localhost or TABMANAGER_DEBUG must be true.\n");
    exit(1);
}

if ($status !== 200) {
    fwrite(STDERR, "Unexpected HTTP $status from $url\n");
    exit(1);
}

$data = json_decode($raw, true);

if ($data === null) {
    fwrite(STDERR, "Invalid JSON response from $url\n");
    exit(1);
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
