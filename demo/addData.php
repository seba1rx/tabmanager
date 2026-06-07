<?php
/**
 * addData.php — Write tab-specific session data (demo endpoint)
 *
 * INTEGRATION STEP 5 (backend): this file shows how to use TabManager::set()
 * to write data that belongs exclusively to the calling tab.
 *
 * TabManager resolves the tab UUID automatically from the X-TabManager-TabId
 * header — you never read or pass the UUID manually. The data is stored under
 * $_SESSION['tabmanager']['tabs'][<uuid>]['data'] and is invisible to other tabs.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/WordStringGenerator.php';

session_start();

$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
if (!$tabId || !Seba1rx\TabManager\TabManager::isValidTabId($tabId)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or missing tab ID']);
    exit;
}

// INTEGRATION: instantiate TabManager — it reads the session and resolves the
// tab UUID from the X-TabManager-TabId request header internally.
$tabManager = new Seba1rx\TabManager\TabManager();

// App logic: generate a random value to store (demo only)
$generator = new WordStringGenerator();
$value = $generator->generate(3);
$key = (string) time();

// INTEGRATION: set() writes to this tab's isolated session slot.
// Other tabs cannot read or overwrite this value.
$tabManager->set($key, $value);

// Return the full session so the UI can re-render all tabs' data
header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);
