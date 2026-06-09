<?php
declare(strict_types=1);
/**
 * tab_close.php — Tab close endpoint (demo workaround for php -S)
 *
 * Marks the tab as inactive when the browser fires beforeunload.
 * Called via navigator.sendBeacon (fire-and-forget) — no response body needed.
 *
 * When /tabmanager/tab-close IS reachable (frameworks with a catch-all route)
 * you do NOT need this file — that endpoint is registered automatically.
 *
 * index.php sets window.TABMANAGER_TAB_CLOSE_URL = 'tab_close.php'.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

use Seba1rx\TabManager\TabManager;

$input = json_decode(file_get_contents('php://input'), true);
$tabId = $input['tab_id'] ?? null;

if ($tabId && TabManager::isValidTabId($tabId)) {
    $tabManager = new TabManager();
    $tabManager->markInactiveTab($tabId);
}

http_response_code(200);
