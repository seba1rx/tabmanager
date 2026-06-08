<?php
namespace Seba1rx\TabManager;

use Seba1rx\TabManager\Contracts\SessionStoreInterface;

/**
 * TabManager
 * Provides per-tab session isolation using a browser cookie and JS client.
 * Session access is delegated to a SessionStoreInterface implementation,
 * defaulting to PhpSessionStore (native PHP sessions) when none is provided.
 */
class TabManager
{
    public const SESSION_KEY = 'tabs';

    protected SessionStoreInterface $store;

    /**
     * @param SessionStoreInterface|null $store
     *   Optional session store. Defaults to PhpSessionStore (native PHP sessions).
     *   Pass a custom implementation to integrate with an existing session layer
     *   (e.g. a wrapper around another package that has already started the session).
     */
    public function __construct(?SessionStoreInterface $store = null)
    {
        $this->store = $store ?? new PhpSessionStore();
        $this->store->start();

        if (!$this->store->has('tabmanager')) {
            $this->store->set('tabmanager', ['tabs' => []]);
        } else {
            $tabmanager = $this->store->get('tabmanager');
            if (!isset($tabmanager['tabs'])) {
                $tabmanager['tabs'] = [];
                $this->store->set('tabmanager', $tabmanager);
            }
        }
    }

    /**
     * Creates the tab id index in the tabs index in the session store.
     * Idempotent: if the tab is already registered, does nothing.
     *
     * @param string $tabId
     * @return void
     */
    public function indexNewTab(string $tabId): void
    {
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        if (!isset($tabmanager['tabs'][$tabId])) {
            $tabmanager['tabs'][$tabId] = [
                'data'        => [],
                'is_active'   => true,
                'last_active' => time(),
            ];
            $this->store->set('tabmanager', $tabmanager);
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
     * Set session data for this tab.
     * No-op if the tab is not registered (SEC-04: prevents implicit slot creation).
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $tabId      = $this->getTabId();
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);

        // SEC-04: only write to tabs that were explicitly registered via
        // indexNewTab(). Avoids implicit session slot creation from arbitrary
        // header/cookie values.
        if (!$tabId || !isset($tabmanager['tabs'][$tabId])) return;

        $tabmanager['tabs'][$tabId]['data'][$key]  = $value;
        $tabmanager['tabs'][$tabId]['is_active']   = true;
        $tabmanager['tabs'][$tabId]['last_active']  = time();
        $this->store->set('tabmanager', $tabmanager);
    }

    /**
     * Get session data for this tab.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $tabId      = $this->getTabId();
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        return $tabmanager['tabs'][$tabId]['data'][$key] ?? $default;
    }

    /**
     * Destroy all session data for a given tab.
     *
     * @param string $tabId
     * @return void
     */
    public function destroyTabSession(string $tabId): void
    {
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        if (isset($tabmanager['tabs'][$tabId])) {
            unset($tabmanager['tabs'][$tabId]);
            $this->store->set('tabmanager', $tabmanager);
        }
    }

    /**
     * Mark a tab as inactive (used on beforeunload).
     *
     * @param string $tabId
     * @return void
     */
    public function markInactiveTab(string $tabId): void
    {
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        if (isset($tabmanager['tabs'][$tabId])) {
            $tabmanager['tabs'][$tabId]['is_active'] = false;
            $this->store->set('tabmanager', $tabmanager);
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
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        if (!isset($tabmanager['tabs'][$tabId])) {
            $this->indexNewTab($tabId);
            return;
        }
        $tabmanager['tabs'][$tabId]['is_active']  = true;
        $tabmanager['tabs'][$tabId]['last_active'] = time();
        $this->store->set('tabmanager', $tabmanager);
    }

    /**
     * Returns whether a tab is currently indexed in the session.
     * When called without an argument the current tab ID is resolved via
     * getTabId() (header → cookie). Returns false if no ID can be resolved.
     *
     * @param string|null $tabId Specific UUID to check, or null to use current tab.
     * @return bool
     */
    public function isTabIndexed(?string $tabId = null): bool
    {
        $id = $tabId ?? $this->getTabId();
        if (!$id) return false;

        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        return isset($tabmanager['tabs'][$id]);
    }

    /**
     * Removes inactive tabs whose last_active timestamp is older than
     * $olderThanSeconds seconds. Active tabs are never removed regardless of age.
     * Returns the number of tabs removed.
     *
     * @param int $olderThanSeconds Tabs inactive for longer than this are deleted.
     *                              Values <= 0 are treated as a no-op and return 0.
     * @return int Number of tabs removed.
     */
    public function cleanupInactiveTabs(int $olderThanSeconds): int
    {
        if ($olderThanSeconds <= 0) return 0;

        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        $cutoff     = time() - $olderThanSeconds;
        $removed    = 0;

        foreach ($tabmanager['tabs'] as $tabId => $tab) {
            if (($tab['is_active'] ?? true) === false
                && ($tab['last_active'] ?? PHP_INT_MAX) < $cutoff
            ) {
                unset($tabmanager['tabs'][$tabId]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->store->set('tabmanager', $tabmanager);
        }

        return $removed;
    }

    /**
     * Return all tab session data for debugging.
     *
     * @return array
     */
    public function debug(): array
    {
        $tabmanager = $this->store->get('tabmanager', ['tabs' => []]);
        $result     = [];

        foreach ($tabmanager['tabs'] as $tabId => $data) {
            $result[$tabId] = [
                'is_active'   => $data['is_active'] ?? false,
                'last_active' => date('Y-m-d H:i:s', $data['last_active'] ?? 0),
                'keys'        => isset($data['data']) ? array_keys($data['data']) : [],
                'size'        => isset($data['data']) ? strlen(json_encode($data['data'])) : 0,
            ];
        }

        return $result;
    }

    /**
     * Generates a UUID v4 (format: 8-4-4-4-12, example: 6ff19a11-97cb-4060-b68f-3b81836ec5f0)
     * (not used by the main flow — JS generates the UUID)
     *
     * @return string UUID v4 lowercase
     */
    public function uuid_v4(): string {
        $data = random_bytes(16);

        // Adjust the version to 0100 (v4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Adjust the variation to 10xx (RFC 4122)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

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
     * Validates if a string is indeed a UUID (v1..v5).
     * (not used by the package itself — superseded by isValidTabId() for internal use)
     *
     * @param string $uuid
     * @param bool   $onlyV4
     * @return bool
     */
    public function is_valid_uuid(string $uuid, bool $onlyV4 = true): bool {
        $pattern = $onlyV4
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
            : '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return (bool) preg_match($pattern, $uuid);
    }
}
