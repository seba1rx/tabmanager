<?php
/**
 * heartbeat.php — Heartbeat endpoint (demo workaround for php -S)
 *
 * INTEGRATION STEP 6 (backend): this file shows how to expose a custom
 * heartbeat endpoint when the default /tabmanager/heartbeat is not reachable.
 *
 * The JS client POSTs here every 30 s (while the tab is visible) to keep
 * last_active fresh in $_SESSION. The backend uses this timestamp to know
 * whether a tab is still alive (useful for session cleanup / GC).
 *
 * When the default /tabmanager/heartbeat IS reachable (frameworks, SPAs with
 * a catch-all route) you do NOT need this file — that endpoint is registered
 * automatically by the bootstrap.
 *
 * This demo uses `php -S` without a router, so /tabmanager/* returns 404.
 * index.php sets window.TABMANAGER_HEARTBEAT_URL = 'heartbeat.php' to point
 * the JS client here instead.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// INTEGRATION: read the tab UUID from the header sent by TabManagerClient.getHeaders()
$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;

if ($tabId && Seba1rx\TabManager\TabManager::isValidTabId($tabId)) {
    // INTEGRATION: touchTab() updates last_active + is_active = true.
    // It does NOT modify the tab's data slot.
    $tabManager = new Seba1rx\TabManager\TabManager();
    $tabManager->touchTab($tabId);
}

// 204 No Content — the JS client does not expect a response body.
http_response_code(204);
