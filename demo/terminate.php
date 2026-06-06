<?php
/**
 * terminate.php — Destroy the entire session and redirect to the demo home.
 *
 * This is a demo utility, not part of the TabManager integration itself.
 * It wipes $_SESSION completely (all tabs) so you can start fresh.
 *
 * In a real app you would call $tabManager->destroyTabSession($tabId) to
 * remove only the current tab's data, or session_destroy() to wipe everything.
 */

require __DIR__ . '/bootstrap.php';

session_start();
$_SESSION = [];
session_destroy();

header('Location: index.php');
