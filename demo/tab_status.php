<?php
declare(strict_types=1);
/**
 * tab_status.php — Tab existence check endpoint (demo workaround for php -S)
 *
 * INTEGRATION STEP: this file shows how to expose a custom tab-status endpoint
 * when the default /tabmanager/tab-status is not reachable (e.g. `php -S`
 * without a router).
 *
 * For a custom session name or third-party session package, set up your session
 * first, then call TabManager::handleTabStatusRequest() — it reads X-TabManager-TabId,
 * validates it, checks isTabIndexed(), and outputs the JSON response. It does NOT
 * call session_start(). Example with a custom session name:
 *
 *   session_name('MY_APP');
 *   session_start();
 *   TabManager::handleTabStatusRequest();
 *
 * When /tabmanager/tab-status IS reachable (frameworks with a catch-all route)
 * you do NOT need this file — that endpoint is registered automatically.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

use Seba1rx\TabManager\TabManager;

TabManager::handleTabStatusRequest();
