<?php
/**
 * bootstrap.php — Demo-only session configuration
 *
 * This file is NOT part of the TabManager integration — it exists only to
 * redirect PHP's session storage to demo/sessions/ so that:
 *   1. Session files are isolated from the system default (/tmp or similar)
 *   2. The cli_dump.php tool can read them from a predictable location
 *
 * In a real app you would configure session storage in your framework or
 * php.ini and would not need this file at all.
 *
 * Must be included BEFORE session_start() and BEFORE vendor/autoload.php.
 */

$sessionsDir = __DIR__ . '/sessions';
if (!is_dir($sessionsDir)) {
    mkdir($sessionsDir, 0755);
}
session_save_path($sessionsDir);
