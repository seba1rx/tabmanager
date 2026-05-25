<?php
require __DIR__ . '/bootstrap.php';
include __DIR__ . '/../vendor/autoload.php';
session_start();

// Register the tab if not yet indexed. indexNewTab() is idempotent so
// calling it on every page load is safe — it only writes when the entry
// doesn't exist yet. This removes the need for a separate /tabmanager/new-tab
// call from the JS client and eliminates the router dependency.
$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
if ($tabId) {
    $tabManager = new Seba1rx\TabManager\TabManager();
    $tabManager->indexNewTab($tabId);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);
