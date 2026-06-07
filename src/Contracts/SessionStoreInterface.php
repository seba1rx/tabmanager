<?php

declare(strict_types=1);

namespace Seba1rx\TabManager\Contracts;

interface SessionStoreInterface
{
    /**
     * Ensures the session is started.
     * Must be idempotent: if the session is already active, do nothing.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Returns whether the session is currently active.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Returns the value stored at $key in the session root,
     * or $default if the key is not present.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores $value at $key in the session root.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Returns whether $key exists in the session root.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Removes $key from the session root.
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;
}
