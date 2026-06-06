<?php
/**
 * PHPUnit bootstrap for seba1rx/tabmanager.
 *
 * Starts a PHP session backed by a null save handler so tests can read and
 * write $_SESSION without touching the filesystem. Because the session is
 * started here, TabManager::__construct() will see PHP_SESSION_ACTIVE and
 * skip its own session_start() call, keeping tests isolated.
 *
 * bin/bootstrap.php is loaded via Composer's "files" autoload but it only
 * intercepts matching REQUEST_URIs. During CLI (PHPUnit) REQUEST_URI is not
 * set so no endpoint is triggered — safe to load.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Null save handler: session data lives in memory only, no files written.
session_set_save_handler(
    open:    fn(string $path, string $name): bool => true,
    close:   fn(): bool => true,
    read:    fn(string $id): string => '',
    write:   fn(string $id, string $data): bool => true,
    destroy: fn(string $id): bool => true,
    gc:      fn(int $max_lifetime): int|false => 0,
);
session_start();
