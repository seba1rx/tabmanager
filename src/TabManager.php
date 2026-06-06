<?php
namespace Seba1rx\TabManager;

use Seba1rx\TabManager\TabManagerException;

/**
 * TabManager
 * Provides per-tab session isolation using a browser cookie and JS client
 */
class TabManager
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Session is not started yet
            session_start();
        }
        if (!isset($_SESSION['tabmanager'])) $_SESSION['tabmanager'] = [];
        if (!isset($_SESSION['tabmanager']['tabs'])) $_SESSION['tabmanager']['tabs'] = [];
    }

    /**
     * Creates the tab id index in the tabs index in the $_SESSION array
     *
     * @param string $tabId
     * @return void
     */
    public function indexNewTab(string $tabId): void
    {
        if (!isset($_SESSION['tabmanager']['tabs'][$tabId])) {
            $_SESSION['tabmanager']['tabs'][$tabId] = [];

            $_SESSION['tabmanager']['tabs'][$tabId]['data'] = [];
            $_SESSION['tabmanager']['tabs'][$tabId]['is_active'] = true;
            $_SESSION['tabmanager']['tabs'][$tabId]['last_active'] = time();
        }
    }

    /**
     * Validates that a string is a well-formed UUID v4.
     * Used by bootstrap.php to reject arbitrary strings before they reach
     * the session. Also available to consumers for their own validation.
     *
     * @param string $id
     * @return bool
     */
    public static function isValidTabId(string $id): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    /**
     * Get current tab ID.
     * Header takes precedence over cookie: cookies are shared across all tabs
     * in the same browser session, while the header is sent per-request with
     * the correct tab UUID from sessionStorage.
     *
     * @return string|null
     */
    protected function getTabId(): ?string
    {
        return $_SERVER['HTTP_X_TABMANAGER_TABID']
            ?? $_COOKIE['TABMANAGER_TABID']
            ?? null;
    }

    /**
     * Get current tab ID from the request header only — no cookie fallback.
     * Use this in endpoints that handle sensitive data and must not accept
     * the shared cookie as a tab identifier.
     *
     * @return string|null
     */
    public function getTabIdStrict(): ?string
    {
        return $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
    }

    /**
     * Set session data for this tab
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $tabId = $this->getTabId();
        // SEC-04: only write to tabs that were explicitly registered via
        // indexNewTab(). Avoids implicit session slot creation from arbitrary
        // header/cookie values.
        if (!$tabId || !isset($_SESSION['tabmanager']['tabs'][$tabId])) return;

        $_SESSION['tabmanager']['tabs'][$tabId]['data'][$key] = $value;
        $_SESSION['tabmanager']['tabs'][$tabId]['is_active'] = true;
        $_SESSION['tabmanager']['tabs'][$tabId]['last_active'] = time();
    }

    /**
     * Get session data for this tab
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tabId = $this->getTabId();
        return $_SESSION['tabmanager']['tabs'][$tabId]['data'][$key] ?? $default;
    }

    /**
     * Destroy all session data for a given tab
     *
     * @param string $tabId
     * @return void
     */
    public function destroyTabSession(string $tabId): void
    {
        if (isset($_SESSION['tabmanager']['tabs'][$tabId])) {
            unset($_SESSION['tabmanager']['tabs'][$tabId]);
        }
    }

    /**
     * Mark a tab as inactive (used on beforeunload)
     *
     * @param string $tabId
     * @return void
     */
    public function markInactiveTab(string $tabId): void
    {
        if (isset($_SESSION['tabmanager']['tabs'][$tabId])) {
            $_SESSION['tabmanager']['tabs'][$tabId]['is_active'] = false;
        }
    }

    /**
     * Update last_active timestamp for a tab (used by the JS heartbeat).
     * Keeps the tab alive in the session without modifying its data.
     * If the tab is not yet indexed it is created via indexNewTab().
     *
     * @param string $tabId
     * @return void
     */
    public function touchTab(string $tabId): void
    {
        if (!isset($_SESSION['tabmanager']['tabs'][$tabId])) {
            $this->indexNewTab($tabId);
            return;
        }
        $_SESSION['tabmanager']['tabs'][$tabId]['is_active'] = true;
        $_SESSION['tabmanager']['tabs'][$tabId]['last_active'] = time();
    }

    /**
     * Return all tab session data for debugging
     *
     * @return array
     */
    public function debug(): array
    {
        $result = [];
        foreach ($_SESSION['tabmanager']['tabs'] ?? [] as $tabId => $data) {
            $result[$tabId] = [
                'is_active' => $data['is_active'] ?? false,
                'last_active' => date('Y-m-d H:i:s', $data['last_active'] ?? 0),
                'keys' => isset($data['data']) ? array_keys($data['data']) : [],
                'size' => isset($data['data']) ? strlen(json_encode($data['data'])) : 0,
            ];
        }

        return $result;
    }

    /**
     * Gets the key used to index the tabs
     *
     * @return string
     */
    public function getSessionKey(): string
    {
        return 'tabs';
    }

    /**
     * Generates a UUID v4 (format: 8-4-4-4-12, example: 6ff19a11-97cb-4060-b68f-3b81836ec5f0)
     * * (not being used in current version)
     * @return string UUID v4 lowercase
     * @throws TabManagerException
     */
    function uuid_v4(): string {
        $data = random_bytes(16);

        // Adjust the version to  0100 (v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Adjust the variation to 10xx (RFC 4122)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Format as hexadecimal string
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    /**
     * Validates if a string is indeed a UUID (v1..v5)
     * * (not being used in current version)
     * @param string $uuid
     * @param bool $onlyV4
     * @return bool
     */
    function is_valid_uuid(string $uuid, bool $onlyV4 = true): bool {
        $pattern = $onlyV4
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
            : '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return (bool) preg_match($pattern, $uuid);
    }
}