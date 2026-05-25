<?php
/**
 * Dumps all TabManager session data to stdout as pretty-printed JSON.
 * Reads session files directly from demo/sessions/ (set by bootstrap.php).
 *
 * Usage:
 *   php cli_dump.php              — dump all sessions
 *   php cli_dump.php <session_id> — dump a specific session
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/bootstrap.php';

$savePath     = session_save_path();
$requestedSid = $argv[1] ?? null;

fwrite(STDERR, "Reading sessions from: $savePath\n\n");

// Null save handler: lets session_decode() work without touching any file
session_set_save_handler(
    fn($path, $name) => true,
    fn() => true,
    fn($id) => '',
    fn($id, $data) => true,
    fn($id) => true,
    fn($max) => true
);
session_start();

if ($requestedSid !== null) {
    $file = "$savePath/sess_$requestedSid";
    if (!is_readable($file)) {
        fwrite(STDERR, "Session not found: $requestedSid\n");
        exit(1);
    }
    echo json_encode(parseSessionFile($file), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

$files = glob("$savePath/sess_*") ?: [];

if (empty($files)) {
    fwrite(STDERR, "No sessions found.\n");
    echo json_encode([], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$all = [];
foreach ($files as $file) {
    $sid        = substr(basename($file), 5); // strip 'sess_'
    $all[$sid]  = parseSessionFile($file);
}

echo json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// ---------------------------------------------------------------------------

function parseSessionFile(string $file): array
{
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return [];

    $_SESSION = [];
    session_decode($raw);
    $data = $_SESSION;
    $_SESSION = [];

    return $data;
}
