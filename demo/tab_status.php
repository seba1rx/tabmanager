<?php
declare(strict_types=1);
/**
 * tab_status.php — Tab existence check endpoint (demo workaround for php -S)
 *
 * INTEGRATION STEP: this file shows how to expose a custom tab-status endpoint
 * when the default /tabmanager/tab-status is not reachable.
 *
 * Returns { "indexed": bool, "tab_id": "<uuid>" } — whether the current tab's
 * session slot still exists server-side. Called by the JS client on every
 * visibilitychange to visible; if indexed is false, it dispatches the
 * 'tabmanager:session-lost' event so the app can warn the user.
 *
 * When /tabmanager/tab-status IS reachable (frameworks with a catch-all route)
 * you do NOT need this file — that endpoint is registered automatically.
 *
 * This demo runs with `php -S` which serves files directly without a router,
 * so /tabmanager/* paths return 404.
 * index.php sets window.TABMANAGER_TAB_STATUS_URL = 'tab_status.php'.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

use Seba1rx\TabManager\TabManager;

$tabId   = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
$indexed = false;

if ($tabId && TabManager::isValidTabId($tabId)) {
    $tabManager = new TabManager();
    $indexed    = $tabManager->isTabIndexed($tabId);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['indexed' => $indexed, 'tab_id' => $tabId]);
