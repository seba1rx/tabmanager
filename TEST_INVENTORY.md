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
| 1 | Session is empty → initializes `$_SESSION['tabmanager']['tabs']` as array | Structure present |
| 2 | `$_SESSION['tabmanager']['tabs']` already has data → not overwritten | Existing data preserved |
| 3 | Other `$_SESSION` keys present → not modified | Keys intact |

---

## `TabManager::indexNewTab(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | New tab ID → creates entry with `data = []` | `data` key is empty array |
| 2 | New tab ID → `is_active` defaults to `true` | `is_active === true` |
| 3 | New tab ID → `last_active` set to current unix timestamp | Within 2 s of `time()` |
| 4 | Same tab ID called twice → second call is no-op (idempotent) | Data from first call preserved |

---

## `TabManager::touchTab(string $tabId)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab → `last_active` updated | Within 2 s of `time()` |
| 2 | Tab that was marked inactive → `is_active` set back to `true` | `is_active === true` |
| 3 | Registered tab → `data` not modified | `data` unchanged |
| 4 | Unregistered tab ID → tab created via `indexNewTab` | Tab entry now exists |

---

## `TabManager::set(string $key, mixed $value)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Registered tab, valid header → value stored under correct key | `data[$key] === $value` |
| 2 | Registered tab → `last_active` updated on write | Within 2 s of `time()` |
| 3 | Registered tab → `is_active` set to `true` on write | `is_active === true` |
| 4 | Tab not yet registered → no-op, no session entry created (SEC-04) | Entry absent from session |
| 5 | No header and no cookie present → no-op | Session unchanged |
| 6 | Existing key overwritten → new value wins | `data[$key] === $newValue` |
| 7 | Tab identified via cookie fallback (no header) → value stored | `data[$key] === $value` |
| 8 | Various value types: `int`, `array`, `null` → stored without coercion | `===` comparison passes |

---

## `TabManager::get(string $key, mixed $default = null)`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Key exists for registered tab → correct value returned | Returns stored value |
| 2 | Key absent, no default provided → `null` returned | `null` |
| 3 | Key absent, custom default provided → custom default returned | Returns provided default |
| 4 | Tab not registered → default returned | Default, no error |
| 5 | No header and no cookie → default returned | Default, no error |

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
| 2 | Other registered tabs not affected | Sibling tabs intact |
| 3 | Unregistered tab → no-op, no error | Session unchanged |

---

## `TabManager::debug(): array`

| # | Scenario | Expected |
|---|----------|----------|
| 1 | No tabs registered → returns empty array | `[]` |
| 2 | Registered tab → entry has `is_active`, `last_active`, `keys`, `size` | All four keys present |
| 3 | `last_active` formatted as `Y-m-d H:i:s` date string | Matches `date()` format |
| 4 | `keys` contains the data keys (not values) | Array of key names |
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

---

## Not unit-tested (scope note)

The following are excluded from unit tests because they involve HTTP-level behavior (`exit`, superglobal routing, header output) that requires process isolation or integration-level testing:

- `bin/bootstrap.php` endpoint routing and responses
- UUID validation gate in bootstrap (covered indirectly by `isValidTabId` tests)
- Debug endpoint access control (`TABMANAGER_DEBUG` constant)

These are candidates for a future integration test suite.
