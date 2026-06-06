<?php

declare(strict_types=1);

namespace Seba1rx\TabManager\Tests;

use PHPUnit\Framework\TestCase;
use Seba1rx\TabManager\TabManager;

class TabManagerTest extends TestCase
{
    // Known-valid UUID v4 fixtures
    private const UUID_A = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const UUID_B = '6ba7b810-9dad-4d1d-80b4-00c04fd430c8';
    private const UUID_C = 'a3bb189e-8bf9-4a64-8d42-4f3f80f9c9b9';

    protected function setUp(): void
    {
        $_SESSION = [];
        unset($_SERVER['HTTP_X_TABMANAGER_TABID']);
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['HTTP_X_TABMANAGER_TABID']);
        $_COOKIE = [];
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

    private function tabData(string $tabId): array
    {
        return $_SESSION['tabmanager']['tabs'][$tabId] ?? [];
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
        new TabManager();

        $this->assertIsArray($_SESSION['tabmanager']['tabs']);
    }

    public function test_construct_does_not_overwrite_existing_tabs(): void
    {
        $_SESSION['tabmanager']['tabs'][self::UUID_A] = ['data' => ['key' => 'value']];

        new TabManager();

        $this->assertSame('value', $_SESSION['tabmanager']['tabs'][self::UUID_A]['data']['key']);
    }

    public function test_construct_does_not_touch_other_session_keys(): void
    {
        $_SESSION['app_user_id'] = 42;

        new TabManager();

        $this->assertSame(42, $_SESSION['app_user_id']);
    }

    // -------------------------------------------------------------------------
    // indexNewTab
    // -------------------------------------------------------------------------

    public function test_indexNewTab_creates_data_as_empty_array(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame([], $this->tabData(self::UUID_A)['data']);
    }

    public function test_indexNewTab_sets_is_active_true(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_indexNewTab_sets_last_active_to_current_time(): void
    {
        $before = time();
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $after = time();

        $lastActive = $this->tabData(self::UUID_A)['last_active'];
        $this->assertGreaterThanOrEqual($before, $lastActive);
        $this->assertLessThanOrEqual($after, $lastActive);
    }

    public function test_indexNewTab_is_idempotent(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        // Manually write data to the tab
        $_SESSION['tabmanager']['tabs'][self::UUID_A]['data']['existing'] = 'preserved';

        // Second call must not overwrite
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame('preserved', $this->tabData(self::UUID_A)['data']['existing']);
    }

    // -------------------------------------------------------------------------
    // touchTab
    // -------------------------------------------------------------------------

    public function test_touchTab_updates_last_active(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        // Backdate the timestamp
        $_SESSION['tabmanager']['tabs'][self::UUID_A]['last_active'] = time() - 3600;

        $before = time();
        $tm->touchTab(self::UUID_A);
        $after = time();

        $lastActive = $this->tabData(self::UUID_A)['last_active'];
        $this->assertGreaterThanOrEqual($before, $lastActive);
        $this->assertLessThanOrEqual($after, $lastActive);
    }

    public function test_touchTab_reactivates_inactive_tab(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->markInactiveTab(self::UUID_A);

        $this->assertFalse($this->tabData(self::UUID_A)['is_active']);

        $tm->touchTab(self::UUID_A);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_touchTab_does_not_modify_data(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $_SESSION['tabmanager']['tabs'][self::UUID_A]['data']['key'] = 'untouched';

        $tm->touchTab(self::UUID_A);

        $this->assertSame('untouched', $this->tabData(self::UUID_A)['data']['key']);
    }

    public function test_touchTab_creates_tab_if_not_registered(): void
    {
        $tm = new TabManager();

        $this->assertArrayNotHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);

        $tm->touchTab(self::UUID_A);

        $this->assertArrayHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);
    }

    // -------------------------------------------------------------------------
    // set
    // -------------------------------------------------------------------------

    public function test_set_stores_value_for_registered_tab(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->set('role', 'admin');

        $this->assertSame('admin', $this->tabData(self::UUID_A)['data']['role']);
    }

    public function test_set_updates_last_active(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $_SESSION['tabmanager']['tabs'][self::UUID_A]['last_active'] = time() - 3600;

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
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->markInactiveTab(self::UUID_A);

        $tm->set('x', 1);

        $this->assertTrue($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_set_noop_when_tab_not_registered(): void
    {
        // SEC-04: set() must not create a session entry implicitly
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();

        $tm->set('key', 'value');

        $this->assertArrayNotHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);
    }

    public function test_set_noop_when_no_tab_id_present(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->set('key', 'value');

        $this->assertSame([], $this->tabData(self::UUID_A)['data']);
    }

    public function test_set_overwrites_existing_key(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->set('role', 'viewer');
        $tm->set('role', 'admin');

        $this->assertSame('admin', $this->tabData(self::UUID_A)['data']['role']);
    }

    public function test_set_uses_cookie_fallback_when_no_header(): void
    {
        $this->withCookie(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->set('source', 'cookie');

        $this->assertSame('cookie', $this->tabData(self::UUID_A)['data']['source']);
    }

    public function test_set_handles_various_value_types(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->set('int_val',   42);
        $tm->set('arr_val',   ['a', 'b']);
        $tm->set('null_val',  null);

        $data = $this->tabData(self::UUID_A)['data'];
        $this->assertSame(42, $data['int_val']);
        $this->assertSame(['a', 'b'], $data['arr_val']);
        $this->assertNull($data['null_val']);
    }

    // -------------------------------------------------------------------------
    // get
    // -------------------------------------------------------------------------

    public function test_get_returns_stored_value(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->set('color', 'blue');

        $this->assertSame('blue', $tm->get('color'));
    }

    public function test_get_returns_null_when_key_absent(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $this->assertNull($tm->get('missing'));
    }

    public function test_get_returns_custom_default_when_key_absent(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $this->assertSame('fallback', $tm->get('missing', 'fallback'));
    }

    public function test_get_returns_default_when_tab_not_registered(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();

        $this->assertSame('default', $tm->get('key', 'default'));
    }

    public function test_get_returns_default_when_no_tab_id(): void
    {
        $tm = new TabManager();

        $this->assertNull($tm->get('key'));
    }

    // -------------------------------------------------------------------------
    // markInactiveTab
    // -------------------------------------------------------------------------

    public function test_markInactiveTab_sets_is_active_false(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->markInactiveTab(self::UUID_A);

        $this->assertFalse($this->tabData(self::UUID_A)['is_active']);
    }

    public function test_markInactiveTab_preserves_data_and_last_active(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $_SESSION['tabmanager']['tabs'][self::UUID_A]['data']['key'] = 'keep';
        $ts = $_SESSION['tabmanager']['tabs'][self::UUID_A]['last_active'];

        $tm->markInactiveTab(self::UUID_A);

        $this->assertSame('keep', $this->tabData(self::UUID_A)['data']['key']);
        $this->assertSame($ts, $this->tabData(self::UUID_A)['last_active']);
    }

    public function test_markInactiveTab_noop_for_unregistered_tab(): void
    {
        $tm = new TabManager();

        // Must not throw and must not create an entry
        $tm->markInactiveTab(self::UUID_A);

        $this->assertArrayNotHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);
    }

    // -------------------------------------------------------------------------
    // destroyTabSession
    // -------------------------------------------------------------------------

    public function test_destroyTabSession_removes_tab_entry(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $tm->destroyTabSession(self::UUID_A);

        $this->assertArrayNotHasKey(self::UUID_A, $_SESSION['tabmanager']['tabs']);
    }

    public function test_destroyTabSession_does_not_affect_other_tabs(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->indexNewTab(self::UUID_B);

        $tm->destroyTabSession(self::UUID_A);

        $this->assertArrayHasKey(self::UUID_B, $_SESSION['tabmanager']['tabs']);
    }

    public function test_destroyTabSession_noop_for_unregistered_tab(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_B);

        // Must not throw
        $tm->destroyTabSession(self::UUID_A);

        // Sibling must be unaffected
        $this->assertArrayHasKey(self::UUID_B, $_SESSION['tabmanager']['tabs']);
    }

    // -------------------------------------------------------------------------
    // debug
    // -------------------------------------------------------------------------

    public function test_debug_returns_empty_array_when_no_tabs(): void
    {
        $tm = new TabManager();

        $this->assertSame([], $tm->debug());
    }

    public function test_debug_returns_correct_structure(): void
    {
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);

        $result = $tm->debug();

        $this->assertArrayHasKey(self::UUID_A, $result);
        $this->assertArrayHasKey('is_active',   $result[self::UUID_A]);
        $this->assertArrayHasKey('last_active',  $result[self::UUID_A]);
        $this->assertArrayHasKey('keys',         $result[self::UUID_A]);
        $this->assertArrayHasKey('size',         $result[self::UUID_A]);
    }

    public function test_debug_last_active_is_formatted_date_string(): void
    {
        $tm = new TabManager();
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
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->set('name', 'Alice');
        $tm->set('role', 'admin');

        $result = $tm->debug();

        $this->assertContains('name', $result[self::UUID_A]['keys']);
        $this->assertContains('role', $result[self::UUID_A]['keys']);
    }

    public function test_debug_size_matches_json_encoded_data(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();
        $tm->indexNewTab(self::UUID_A);
        $tm->set('x', 'hello');

        $result   = $tm->debug();
        $expected = strlen(json_encode($_SESSION['tabmanager']['tabs'][self::UUID_A]['data']));

        $this->assertSame($expected, $result[self::UUID_A]['size']);
    }

    // -------------------------------------------------------------------------
    // getTabIdStrict
    // -------------------------------------------------------------------------

    public function test_getTabIdStrict_returns_header_value(): void
    {
        $this->withHeader(self::UUID_A);
        $tm = new TabManager();

        $this->assertSame(self::UUID_A, $tm->getTabIdStrict());
    }

    public function test_getTabIdStrict_returns_null_when_only_cookie_present(): void
    {
        $this->withCookie(self::UUID_A);
        $tm = new TabManager();

        $this->assertNull($tm->getTabIdStrict());
    }

    public function test_getTabIdStrict_returns_null_when_nothing_present(): void
    {
        $tm = new TabManager();

        $this->assertNull($tm->getTabIdStrict());
    }
}
