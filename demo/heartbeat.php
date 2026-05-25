<?php
require __DIR__ . '/bootstrap.php';
include __DIR__ . '/../vendor/autoload.php';
session_start();

$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
if ($tabId) {
    $tabManager = new Seba1rx\TabManager\TabManager();
    $tabManager->touchTab($tabId);
}

http_response_code(204);
