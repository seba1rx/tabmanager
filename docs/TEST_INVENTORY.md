# Test Inventory — seba1rx/tabmanager

Maps each testable method to the scenarios that must be covered.
Test file: `tests/TabManagerTest.php` — run with `composer test`.

---

## `TabManager::isValidTabId(string $id): bool` (static)

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Valid UUID v4, lowercase | `true` |
| 2 | Valid UUID v4, uppercase | `true` (case-insensitive) |
| 3 | Valid UUID v4, mixed case | `true` |
| 4 | UUID with version digit ≠ 4 (e.g. v1) | `false` |
| 5 | UUID with variant nibble outside `[89ab]` | `false` |
| 6 | Empty string | `false` |
| 7 | Arbitrary non-UUID string | `false` |
| 8 | String too short | `false` |
| 9 | String too long | `false` |
| 10 | UUID with invalid hex characters | `false` |
| 11 | UUID missing hyphens | `false` |

---

## `TabManager::__construct()`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Session is empty → initializes `$_SESSION['tabmanager']['tabs']` as **empty** array | `tabs === []` (not just any array) |
| 2 | `$_SESSION['tabmanager']['tabs']` already has data → not overwritten | Existing data preserved |
| 3 | Other `$_SESSION` keys present → not modified | Keys intact |
| 4 | No store argument → default `PhpSessionStore` starts PHP session | `session_status() === PHP_SESSION_ACTIVE`, data in `$_SESSION` |
| 5 | Custom store injected → data lives in custom store, not in `$_SESSION` | `$store->has('tabmanager')` is true |

---

## `TabManager::indexNewTab(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | New tab ID → creates entry with `data = []` | `data` key is empty array |
| 2 | New tab ID → `is_active` defaults to `true` | `is_active === true` |
| 3 | New tab ID → `last_active` set to current unix timestamp | Within 2 s of `time()` |
| 4 | Same tab ID called twice → second call is no-op (data, `is_active`, and `last_active` all preserved) | All three fields unchanged |

---

## `TabManager::touchTab(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab → `last_active` updated | Within 2 s of `time()` |
| 2 | Tab that was marked inactive → `is_active` set back to `true` | `is_active === true` |
| 3 | Registered tab → `data` not modified | `data` unchanged |
| 4 | Unregistered tab ID → tab created with correct structure (`data=[]`, `is_active=true`, `last_active` current) | Full valid entry present |

---

## `TabManager::set(string $key, mixed $value)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab, valid header → value stored under correct key | `data[$key] === $value` |
| 2 | Registered tab → `last_active` updated on write | Within 2 s of `time()` |
| 3 | Registered tab, previously inactive → `is_active` set to `true` on write (verified by pre-condition assert) | `is_active === true` |
| 4 | Tab not yet registered → no-op, no session entry created (SEC-04) | Entry absent from session |
| 5 | No header and no cookie present → no-op | Session unchanged |
| 6 | Existing key overwritten → new value wins | `data[$key] === $newValue` |
| 7 | Tab identified via cookie fallback (no header) → value stored | `data[$key] === $value` |
| 8 | Header and cookie both present → header wins (tab isolation guarantee) | Data lands in header tab, not cookie tab |
| 9 | Various value types: `int`, `array`, `null` → stored without coercion | `===` comparison passes |

---

## `TabManager::get(string $key, mixed $default = null)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Key exists for registered tab → correct value returned | Returns stored value |
| 2 | Key absent, no default provided → `null` returned | `null` |
| 3 | Key absent, custom default provided → custom default returned | Returns provided default |
| 4 | Tab not registered → default returned | Default, no error |
| 5 | No header and no cookie → default returned | Default, no error |
| 6 | Tab A has a key; request is for Tab B → Tab B sees `null` (no cross-tab leak) | `null` |

---

## `TabManager::markInactiveTab(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered active tab → `is_active` set to `false` | `is_active === false` |
| 2 | `data` and `last_active` not modified | Values unchanged |
| 3 | Unregistered tab → no-op, no error | Session unchanged |

---

## `TabManager::destroyTabSession(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab → entry removed from session | Tab key absent |
| 2 | Other registered tabs not affected → sibling key present **and** sibling data intact | Both key and data survive |
| 3 | Unregistered tab → no-op, no error | Session unchanged |

---

## `TabManager::isTabIndexed(?string $tabId = null): bool`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab, explicit UUID argument → `true` | `true` |
| 2 | Unregistered tab, explicit UUID argument → `false` | `false` |
| 3 | Two tabs registered; check each → correct result per UUID | `true`/`false` respectively |
| 4 | No argument, header present, tab registered → resolves via header | `true` |
| 5 | No argument, no header, no cookie → `false` | `false` |
| 6 | Tab marked inactive (but still registered) → `true` | Inactive ≠ unregistered |
| 7 | Tab destroyed → `false` | `false` |

---

## `TabManager::cleanupInactiveTabs(int $olderThanSeconds): int`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Stale inactive tab (2 h ago, threshold 1 h) → removed | Returns `1`; tab key absent |
| 2 | Active tab with old timestamp → preserved | Returns `0`; tab key present |
| 3 | Inactive tab but recent (100 s, threshold 1 h) → preserved | Returns `0`; tab key present |
| 4 | No eligible tabs → returns `0` and tab is still present | `0`, key still exists |
| 5 | Threshold `0` → no-op, eligible tab preserved | Returns `0`; tab key present |
| 6 | Threshold `-1` → no-op (same guard as `0`) | Returns `0`; tab key present |
| 7 | Multiple tabs: only one eligible → removes exactly that one, preserves others | Returns `1`; correct tabs remain |

---

## `TabManager::debug(): array`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | No tabs registered → returns empty array | `[]` |
| 2 | Registered tab → entry has `is_active`, `last_active`, `keys`, `size` | All four keys present |
| 3 | `last_active` formatted as `Y-m-d H:i:s` date string | Matches `date()` format |
| 4 | `keys` contains exactly the data key names (not values, not extras) | Correct count; values absent |
| 5 | `size` matches byte length of `json_encode($data)` | Integer, correct size |

---

## `TabManager::getTabIdStrict(): ?string`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | `X-TabManager-TabId` header present → returns header value | Correct UUID string |
| 2 | Header absent, cookie present → returns `null` (no fallback) | `null` |
| 3 | Both header and cookie absent → returns `null` | `null` |

---

## `TabManager::getTabId()` (protected — tested indirectly)

Covered through `set()` and `get()` behavior:

| # | Scenario | Covered by |
|---|----------|------------|
| 1 | Header present → header value used | `set` test #1 |
| 2 | Header absent, cookie present → cookie used | `set` test #7 |
| 3 | Neither present → `null`, no-op | `set` test #5 |
| 4 | Both header and cookie present → header wins | `set` test #8 |

---

## Not unit-tested (scope note)

The following are excluded from unit tests because they involve HTTP-level behavior (`exit`, superglobal routing, header output) that requires process isolation or integration-level testing:

- `bin/bootstrap.php` endpoint routing and responses
- UUID validation gate in bootstrap (covered indirectly by `isValidTabId` tests)
- Debug endpoint access control (`TABMANAGER_DEBUG` constant)

These are candidates for a future integration test suite.
