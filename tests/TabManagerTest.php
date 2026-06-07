<?php

declare(strict_types=1);

namespace Seba1rx\TabManager\Tests;

use PHPUnit\Framework\TestCase;
use Seba1rx\TabManager\Contracts\SessionStoreInterface;
use Seba1rx\TabManager\TabManager;

// ---------------------------------------------------------------------------
// In-memory session store for unit tests.
// Uses a plain PHP array — no $_SESSION access, no files written, fully isolated.
// ---------------------------------------------------------------------------

final class ArraySessionStore implements SessionStoreInterface
{
    private array $data   = [];
    private bool  $active = false;

    public function start(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}

// ---------------------------------------------------------------------------

class TabManagerTest extends TestCase
{
    // Known-valid UUID v4 fixtures
    private const UUID_A = 'f47ac10b-58cc-4372-a567-0e02b2c3d479'; // variant a ✓
    private const UUID_B = '6ba7b810-9dad-4d1d-80b4-00c04fd430c8'; // variant 8 ✓
    private const UUID_C = 'a3bb189e-8bf9-4a64-8d42-4f3f80f9c9b9'; // variant 8 ✓

    private ArraySessionStore $store;

    protected function setUp(): void
    {
        $this->store = new ArraySessionStore();
        unset($_SERVER['HTTP_X_TABMANAGER_TABID']);
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_TABMANAGER_TABID']);
        $_COOKIE = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function withHeader(string $tabId): void
    {
        $_SERVER['HTTP_X_TABMANAGER_TABID'] = $tabId;
    }

    private function withCookie(string $tabId): void
    {
        $_COOKIE['TABMANAGER_TABID'] = $tabId;
    }

    /** Returns the raw session data for a given tab from the in-memory store. */
    private function tabData(string $tabId): array
    {
        return $this->store->get('tabmanager', ['tabs' => []])['tabs'][$tabId] ?? [];
    }

    /** Returns the full tabs array from the in-memory store. */
    private function tabs(): array
    {
        return $this->store->get('tabmanager', ['tabs' => []])['tabs'];
    }

    // -------------------------------------------------------------------------
    // isValidTabId
    // -------------------------------------------------------------------------

    public function test_isValidTabId_valid_lowercase(): void
    {
        $this->assertTrue(TabManager::isValidTabId(self::UUID_A));
    }

    public function test_isValidTabId_valid_uppercase(): void
    {
        $this->assertTrue(TabManager::isValidTabId(strtoupper(self::UUID_A)));
    }

    public function test_isValidTabId_valid_mixed_case(): void
    {
        $this->assertTrue(TabManager::isValidTabId('F47AC10B-58CC-4372-A567-0e02b2c3d479'));
    }

    public function test_isValidTabId_wrong_version_digit(): void
    {
        // Version digit is 1, not 4
        $this->assertFalse(TabManager::isValidTabId('f47ac10b-58cc-1372-a567-0e02b2c3d479'));
    }

    public function test_isValidTabId_wrong_variant_nibble(): void
    {
        // Variant nibble is 'c', not in [89ab]
        $this->assertFalse(TabManager::isValidTabId('f47ac10b-58cc-4372-c567-0e02b2c3d479'));
    }

    public function test_isValidTabId_empty_string(): void
    {
        $this->assertFalse(TabManager::isValidTabId(''));
    }

    public function test_isValidTabId_arbitrary_string(): void
    {
        $this->assertFalse(TabManager::isValidTabId('not-a-uuid'));
    }

    public function test_isValidTabId_too_short(): void
    {
        $this->assertFalse(TabManager::isValidTabId('f47ac10b-58cc-4372-a567'));
    }

    public function test_isValidTabId_too_long(): void
    {
        $this->assertFalse(TabManager::isValidTabId(self::UUID_A . '-extra'));
    }

    public function test_isValidTabId_invalid_hex_chars(): void
    {
        $this->assertFalse(TabManager::isValidTabId('z47ac10b-58cc-4372-a567-0e02b2c3d479'));
    }

    public function test_isValidTabId_missing_hyphens(): void
    {
        $this->assertFalse(TabManager::isValidTabId('f47ac10b58cc4372a5670e02b2c3d479'));
    }

    // -------------------------------------------------------------------------
    // __construct — session initialization
    // -------------------------------------------------------------------------

    public function test_construct_initializes_session_structure(): void
    {
        new TabManager($this->store);

        $tabmanager = $this->store->get('tabmanager');
        $this->assertIsArray($tabmanager);
        $this->assertSame([], $tabmanager['tabs']);
    }

    public function test_construct_does_not_overwrite_existing_tabs(): void
    {
        $this->store->set('tabmanager', [
            'tabs' => [self::UUID_A => ['data' => ['key' => 'value']]],
        ]);

        new TabManager($this->store);

        $this->assertSame('value', $this->store->get('tabmanager')['tabs'][self::UUID_A]['data']['key']);
    }

    public function test_construct_does_not_touch_other_session_keys(): void
    {
        $this->store->set('app_user_id', 42);

        new TabManager($this->store);

        $this->assertSame(42, $this->store->get('app_user_id'));
    }

    // -------------------------------------------------------------------------
    // indexNewTab
    // -------------------------------------------------------------------------

    public function test_indexNewTab_creates_data_as_empty_array(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame([], $this->tabData(self::UUID_A)['data']);
    }

    public function test_indexNewTab_sets_is_active_true(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_indexNewTab_sets_last_active_to_current_time(): void
    {
        $before = time();
        $tm     = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $after = time();

        $lastActive = $this->tabData(self::UUID_A)['last_active'];
        $this->assertGreaterThanOrEqual($before, $lastActive);
        $this->assertLessThanOrEqual($after, $lastActive);
    }

    public function test_indexNewTab_is_idempotent(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // Snapshot state after first registration and inject extra data
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['data']['existing'] = 'preserved';
        $originalIsActive   = $tabmanager['tabs'][self::UUID_A]['is_active'];
        $originalLastActive = $tabmanager['tabs'][self::UUID_A]['last_active'];
        $this->store->set('tabmanager', $tabmanager);

        // Second call must be a complete no-op
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame('preserved',         $this->tabData(self::UUID_A)['data']['existing']);
        $this->assertSame($originalIsActive,   $this->tabData(self::UUID_A)['is_active']);
        $this->assertSame($originalLastActive, $this->tabData(self::UUID_A)['last_active']);
    }

    // -------------------------------------------------------------------------
    // touchTab
    // -------------------------------------------------------------------------

    public function test_touchTab_updates_last_active(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // Backdate the timestamp via the store
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 3600;
        $this->store->set('tabmanager', $tabmanager);

        $before = time();
        $tm->touchTab(self::UUID_A);
        $after = time();

        $lastActive = $this->tabData(self::UUID_A)['last_active'];
        $this->assertGreaterThanOrEqual($before, $lastActive);
        $this->assertLessThanOrEqual($after, $lastActive);
    }

    public function test_touchTab_reactivates_inactive_tab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->markInactiveTab(self::UUID_A);

        $this->assertFalse($this->tabData(self::UUID_A)['is_active']);

        $tm->touchTab(self::UUID_A);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_touchTab_does_not_modify_data(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['data']['key'] = 'untouched';
        $this->store->set('tabmanager', $tabmanager);

        $tm->touchTab(self::UUID_A);

        $this->assertSame('untouched', $this->tabData(self::UUID_A)['data']['key']);
    }

    public function test_touchTab_creates_tab_if_not_registered(): void
    {
        $tm = new TabManager($this->store);

        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());

        $before = time();
        $tm->touchTab(self::UUID_A);
        $after = time();

        $tab = $this->tabData(self::UUID_A);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
        $this->assertSame([], $tab['data']);
        $this->assertTrue($tab['is_active']);
        $this->assertGreaterThanOrEqual($before, $tab['last_active']);
        $this->assertLessThanOrEqual($after, $tab['last_active']);
    }

    // -------------------------------------------------------------------------
    // set
    // -------------------------------------------------------------------------

    public function test_set_stores_value_for_registered_tab(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->set('role', 'admin');

        $this->assertSame('admin', $this->tabData(self::UUID_A)['data']['role']);
    }

    public function test_set_updates_last_active(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 3600;
        $this->store->set('tabmanager', $tabmanager);

        $before = time();
        $tm->set('x', 1);
        $after = time();

        $lastActive = $this->tabData(self::UUID_A)['last_active'];
        $this->assertGreaterThanOrEqual($before, $lastActive);
        $this->assertLessThanOrEqual($after, $lastActive);
    }

    public function test_set_marks_tab_active(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->markInactiveTab(self::UUID_A);

        $this->assertFalse($this->tabData(self::UUID_A)['is_active']); // pre-condition

        $tm->set('x', 1);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_set_noop_when_tab_not_registered(): void
    {
        // SEC-04: set() must not create a session entry implicitly
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);

        $tm->set('key', 'value');

        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
    }

    public function test_set_noop_when_no_tab_id_present(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->set('key', 'value');

        $this->assertSame([], $this->tabData(self::UUID_A)['data']);
    }

    public function test_set_overwrites_existing_key(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->set('role', 'viewer');
        $tm->set('role', 'admin');

        $this->assertSame('admin', $this->tabData(self::UUID_A)['data']['role']);
    }

    public function test_set_uses_cookie_fallback_when_no_header(): void
    {
        $this->withCookie(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->set('source', 'cookie');

        $this->assertSame('cookie', $this->tabData(self::UUID_A)['data']['source']);
    }

    public function test_set_header_takes_priority_over_cookie(): void
    {
        // Both present — header (UUID_A) must win over cookie (UUID_B)
        $this->withHeader(self::UUID_A);
        $this->withCookie(self::UUID_B);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->indexNewTab(self::UUID_B);

        $tm->set('source', 'header');

        $this->assertSame('header', $this->tabData(self::UUID_A)['data']['source']);
        $this->assertSame([],       $this->tabData(self::UUID_B)['data']);
    }

    public function test_set_handles_various_value_types(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->set('int_val',  42);
        $tm->set('arr_val',  ['a', 'b']);
        $tm->set('null_val', null);

        $data = $this->tabData(self::UUID_A)['data'];
        $this->assertSame(42,         $data['int_val']);
        $this->assertSame(['a', 'b'], $data['arr_val']);
        $this->assertNull($data['null_val']);
    }

    // -------------------------------------------------------------------------
    // get
    // -------------------------------------------------------------------------

    public function test_get_returns_stored_value(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->set('color', 'blue');

        $this->assertSame('blue', $tm->get('color'));
    }

    public function test_get_returns_null_when_key_absent(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertNull($tm->get('missing'));
    }

    public function test_get_returns_custom_default_when_key_absent(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame('fallback', $tm->get('missing', 'fallback'));
    }

    public function test_get_returns_default_when_tab_not_registered(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);

        $this->assertSame('default', $tm->get('key', 'default'));
    }

    public function test_get_returns_default_when_no_tab_id(): void
    {
        $tm = new TabManager($this->store);

        $this->assertNull($tm->get('key'));
    }

    public function test_get_does_not_leak_between_tabs(): void
    {
        $tm = new TabManager($this->store);

        $this->withHeader(self::UUID_A);
        $tm->indexNewTab(self::UUID_A);
        $tm->set('secret', 'alpha');

        // Switch to a different tab — UUID_A's value must not be visible
        $this->withHeader(self::UUID_B);
        $tm->indexNewTab(self::UUID_B);

        $this->assertNull($tm->get('secret'));
    }

    // -------------------------------------------------------------------------
    // markInactiveTab
    // -------------------------------------------------------------------------

    public function test_markInactiveTab_sets_is_active_false(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->markInactiveTab(self::UUID_A);

        $this->assertFalse($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_markInactiveTab_preserves_data_and_last_active(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['data']['key'] = 'keep';
        $ts = $tabmanager['tabs'][self::UUID_A]['last_active'];
        $this->store->set('tabmanager', $tabmanager);

        $tm->markInactiveTab(self::UUID_A);

        $this->assertSame('keep', $this->tabData(self::UUID_A)['data']['key']);
        $this->assertSame($ts,    $this->tabData(self::UUID_A)['last_active']);
    }

    public function test_markInactiveTab_noop_for_unregistered_tab(): void
    {
        $tm = new TabManager($this->store);

        // Must not throw and must not create an entry
        $tm->markInactiveTab(self::UUID_A);

        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
    }

    // -------------------------------------------------------------------------
    // destroyTabSession
    // -------------------------------------------------------------------------

    public function test_destroyTabSession_removes_tab_entry(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tm->destroyTabSession(self::UUID_A);

        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
    }

    public function test_destroyTabSession_does_not_affect_other_tabs(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->indexNewTab(self::UUID_B);

        // Give UUID_B data to verify it survives intact
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_B]['data']['key'] = 'intact';
        $this->store->set('tabmanager', $tabmanager);

        $tm->destroyTabSession(self::UUID_A);

        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
        $this->assertArrayHasKey(self::UUID_B,    $this->tabs());
        $this->assertSame('intact', $this->tabData(self::UUID_B)['data']['key']);
    }

    public function test_destroyTabSession_noop_for_unregistered_tab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_B);

        // Must not throw
        $tm->destroyTabSession(self::UUID_A);

        // Sibling must be unaffected
        $this->assertArrayHasKey(self::UUID_B, $this->tabs());
    }

    // -------------------------------------------------------------------------
    // debug
    // -------------------------------------------------------------------------

    public function test_debug_returns_empty_array_when_no_tabs(): void
    {
        $tm = new TabManager($this->store);

        $this->assertSame([], $tm->debug());
    }

    public function test_debug_returns_correct_structure(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $result = $tm->debug();

        $this->assertArrayHasKey(self::UUID_A, $result);
        $this->assertArrayHasKey('is_active',  $result[self::UUID_A]);
        $this->assertArrayHasKey('last_active', $result[self::UUID_A]);
        $this->assertArrayHasKey('keys',        $result[self::UUID_A]);
        $this->assertArrayHasKey('size',        $result[self::UUID_A]);
    }

    public function test_debug_last_active_is_formatted_date_string(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $result = $tm->debug();

        // Must match Y-m-d H:i:s format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $result[self::UUID_A]['last_active']
        );
    }

    public function test_debug_keys_contains_data_key_names(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->set('name', 'Alice');
        $tm->set('role', 'admin');

        $result = $tm->debug();

        $this->assertCount(2,       $result[self::UUID_A]['keys']);
        $this->assertContains('name', $result[self::UUID_A]['keys']);
        $this->assertContains('role', $result[self::UUID_A]['keys']);
        $this->assertNotContains('Alice', $result[self::UUID_A]['keys']);
        $this->assertNotContains('admin', $result[self::UUID_A]['keys']);
    }

    public function test_debug_size_matches_json_encoded_data(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->set('x', 'hello');

        $result   = $tm->debug();
        $expected = \strlen(\json_encode($this->tabData(self::UUID_A)['data']));

        $this->assertSame($expected, $result[self::UUID_A]['size']);
    }

    // -------------------------------------------------------------------------
    // getTabIdStrict
    // -------------------------------------------------------------------------

    public function test_getTabIdStrict_returns_header_value(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);

        $this->assertSame(self::UUID_A, $tm->getTabIdStrict());
    }

    public function test_getTabIdStrict_returns_null_when_only_cookie_present(): void
    {
        $this->withCookie(self::UUID_A);
        $tm = new TabManager($this->store);

        $this->assertNull($tm->getTabIdStrict());
    }

    public function test_getTabIdStrict_returns_null_when_nothing_present(): void
    {
        $tm = new TabManager($this->store);

        $this->assertNull($tm->getTabIdStrict());
    }

    // -------------------------------------------------------------------------
    // isTabIndexed
    // -------------------------------------------------------------------------

    public function testIsTabIndexedReturnsTrueForRegisteredTab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertTrue($tm->isTabIndexed(self::UUID_A));
    }

    public function testIsTabIndexedReturnsFalseForUnregisteredTab(): void
    {
        $tm = new TabManager($this->store);

        $this->assertFalse($tm->isTabIndexed(self::UUID_A));
    }

    public function testIsTabIndexedWithArgumentChecksSpecificTabId(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $this->assertTrue($tm->isTabIndexed(self::UUID_A));
        $this->assertFalse($tm->isTabIndexed(self::UUID_B));
    }

    public function testIsTabIndexedResolvesCurrentTabFromHeaderWhenNoArgument(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // No argument → resolves UUID_A from X-TabManager-TabId header
        $this->assertTrue($tm->isTabIndexed());
    }

    public function testIsTabIndexedReturnsFalseWhenNoTabIdAndNoArgument(): void
    {
        // No header, no cookie, no argument
        $tm = new TabManager($this->store);

        $this->assertFalse($tm->isTabIndexed());
    }

    public function testIsTabIndexedReturnsTrueForInactiveTab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->markInactiveTab(self::UUID_A);

        // Inactive ≠ unregistered — the slot still exists
        $this->assertTrue($tm->isTabIndexed(self::UUID_A));
    }

    public function testIsTabIndexedReturnsFalseAfterDestroyTabSession(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->destroyTabSession(self::UUID_A);

        $this->assertFalse($tm->isTabIndexed(self::UUID_A));
    }

    // -------------------------------------------------------------------------
    // cleanupInactiveTabs
    // -------------------------------------------------------------------------

    public function testCleanupInactiveTabsRemovesStaleInactiveTab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['is_active']  = false;
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 7200; // 2 h ago
        $this->store->set('tabmanager', $tabmanager);

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(1, $removed);
        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
    }

    public function testCleanupInactiveTabsPreservesActiveTab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // Backdate but keep is_active = true (default from indexNewTab)
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 7200;
        $this->store->set('tabmanager', $tabmanager);

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
    }

    public function testCleanupInactiveTabsPreservesRecentInactiveTab(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // Inactive but only 100 s ago — below the 3600 s threshold
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['is_active']  = false;
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 100;
        $this->store->set('tabmanager', $tabmanager);

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
    }

    public function testCleanupInactiveTabsReturnsZeroWhenNothingToClean(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A); // active + recent by default

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
    }

    public function testCleanupInactiveTabsPartialRemoval(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);
        $tm->indexNewTab(self::UUID_B);
        $tm->indexNewTab(self::UUID_C);

        $tabmanager = $this->store->get('tabmanager');
        // UUID_A: stale + inactive → eligible for removal
        $tabmanager['tabs'][self::UUID_A]['is_active']   = false;
        $tabmanager['tabs'][self::UUID_A]['last_active']  = time() - 7200;
        // UUID_B: active, recent (default from indexNewTab) → not eligible
        // UUID_C: inactive but only 100 s ago → below the 3600 s threshold
        $tabmanager['tabs'][self::UUID_C]['is_active']   = false;
        $tabmanager['tabs'][self::UUID_C]['last_active']  = time() - 100;
        $this->store->set('tabmanager', $tabmanager);

        $removed = $tm->cleanupInactiveTabs(3600);

        $this->assertSame(1, $removed);
        $this->assertArrayNotHasKey(self::UUID_A, $this->tabs());
        $this->assertArrayHasKey(self::UUID_B,    $this->tabs());
        $this->assertArrayHasKey(self::UUID_C,    $this->tabs());
    }

    public function testCleanupInactiveTabsNegativeThreshold(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['is_active']   = false;
        $tabmanager['tabs'][self::UUID_A]['last_active']  = time() - 7200;
        $this->store->set('tabmanager', $tabmanager);

        // Negative threshold is a documented no-op (same guard as 0)
        $removed = $tm->cleanupInactiveTabs(-1);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
    }

    public function testCleanupInactiveTabsReturnsZeroForNonPositiveThreshold(): void
    {
        $tm = new TabManager($this->store);
        $tm->indexNewTab(self::UUID_A);

        // Make it clearly eligible — but threshold 0 must be a no-op
        $tabmanager = $this->store->get('tabmanager');
        $tabmanager['tabs'][self::UUID_A]['is_active']  = false;
        $tabmanager['tabs'][self::UUID_A]['last_active'] = time() - 7200;
        $this->store->set('tabmanager', $tabmanager);

        $removed = $tm->cleanupInactiveTabs(0);

        $this->assertSame(0, $removed);
        $this->assertArrayHasKey(self::UUID_A, $this->tabs());
    }

    // -------------------------------------------------------------------------
    // Constructor store injection
    // -------------------------------------------------------------------------

    public function testConstructorUsesPhpSessionStoreByDefault(): void
    {
        // Smoke test: no store argument → PhpSessionStore → PHP session starts
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertArrayHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);
    }

    public function testConstructorAcceptsCustomStore(): void
    {
        $store = new ArraySessionStore();
        $tm    = new TabManager($store);
        $tm->indexNewTab(self::UUID_A);

        // Data lives in the custom store, not in $_SESSION
        $this->assertTrue($store->has('tabmanager'));
        $this->assertArrayHasKey(self::UUID_A, $store->get('tabmanager')['tabs']);
    }
}
