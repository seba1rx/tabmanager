<?php

declare(strict_types=1);

namespace Seba1rx\TabManager;

use Seba1rx\TabManager\Contracts\SessionStoreInterface;

/**
 * Default session store backed by PHP native sessions.
 * Delegates to session_start() / session_status() and $_SESSION directly.
 * Used by TabManager when no custom store is provided.
 */
class PhpSessionStore implements SessionStoreInterface
{
    /**
     * Starts the session if it is not already active.
     *
     * @return void
     */
    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Returns true when a PHP session is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Returns the value at $key in $_SESSION, or $default if absent.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Writes $value to $_SESSION[$key].
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Returns true if $_SESSION[$key] exists and is not null.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Removes $_SESSION[$key].
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
