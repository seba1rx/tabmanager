<?php
/**
 * session.php — Tab registration + session fetch endpoint
 *
 * INTEGRATION STEP 5 (backend): this file shows the minimal backend integration.
 *
 * What it does:
 *   1. Reads the tab UUID from the X-TabManager-TabId request header
 *   2. Calls indexNewTab() to register the tab in $_SESSION (idempotent — safe
 *      to call on every page load; does nothing if the tab is already registered)
 *   3. Returns the full $_SESSION as JSON so the frontend can render all tabs
 *
 * Why session.php instead of /tabmanager/new-tab:
 *   The built-in /tabmanager/new-tab endpoint works in any setup where all
 *   requests are routed through a PHP front controller. This demo runs with
 *   `php -S` which serves files directly, so /tabmanager/* paths return 404.
 *   session.php is the workaround — it does the same thing but lives at a
 *   directly accessible path.
 *
 * In a real app with a proper router you would NOT need this file — the
 *   built-in endpoint handles registration automatically.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// INTEGRATION: read the tab UUID from the header sent by TabManagerClient.getHeaders()
$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;

if ($tabId && Seba1rx\TabManager\TabManager::isValidTabId($tabId)) {
    // INTEGRATION: register the tab. This is idempotent — calling it multiple
    // times for the same UUID has no effect.
    $tabManager = new Seba1rx\TabManager\TabManager();
    $tabManager->indexNewTab($tabId);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);
