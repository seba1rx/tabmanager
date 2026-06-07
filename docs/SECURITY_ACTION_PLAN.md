# Security Action Plan — seba1rx/tabmanager

Generated from security review of `src/TabManager.php` and `bin/bootstrap.php`.  
Each item documents the vulnerability, the affected code, the mitigation, and its implementation status.

---

## Status legend

| Symbol | Meaning |
|--------|---------|
| ⬜ | Pending |
| 🔄 | In progress |
| ✅ | Implemented |

---

## CRITICAL

---

### SEC-01 — `debug_js` exposes full `$_SESSION` ✅

**File:** `bin/bootstrap.php`  
**Severity:** Critical

**Vulnerability:**  
The `/tabmanager/debug_js` endpoint serialized the entire `$_SESSION` array, not just the `tabmanager` slice. Any data the consuming application stores in the session (auth tokens, user identity, CSRF tokens, etc.) was included in the response.

```php
// before — exposed everything
echo json_encode([
    ...
    'session' => $_SESSION,
]);
```

**Impact:**  
Full session data disclosure to anyone who can reach the endpoint. Combined with SEC-02 (proxy IP bypass), this could be exploited from the public internet without any authentication.

**Mitigation applied:**  
Replaced `$_SESSION` with `$_SESSION['tabmanager'] ?? []` in the JSON output. Key renamed to `tabmanager_session` to make the scope explicit.

```php
// after
echo json_encode([
    ...
    'tabmanager_session' => $_SESSION['tabmanager'] ?? [],
]);
```

**Implementation notes:** Fixed in `bin/bootstrap.php`. 2026-05-25.

---

### SEC-02 — Debug endpoints bypass via reverse proxy `REMOTE_ADDR` ✅

**File:** `bin/bootstrap.php`  
**Severity:** Critical

**Vulnerability:**  
Access control for the three debug/delete endpoints relied on `$_SERVER['REMOTE_ADDR']`:

```php
// before
$allowed =
    (defined('TABMANAGER_DEBUG') && TABMANAGER_DEBUG === true) ||
    in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
```

In any deployment with a reverse proxy (nginx, Apache, load balancer, Cloudflare), `REMOTE_ADDR` is always the proxy's IP — typically `127.0.0.1`. This made every request appear to come from localhost, exposing debug endpoints to the public internet.

**Impact:**  
- `/tabmanager/debug_html` and `/tabmanager/debug_js`: full session data visible to anyone.  
- `/tabmanager/debug/delete-tab`: any visitor could delete any tab's session data.

**Mitigation applied:**  
Removed `REMOTE_ADDR` check entirely. `TABMANAGER_DEBUG` is now the only access gate. Added documentation that this constant must never be defined in production.

```php
// after
if (!defined('TABMANAGER_DEBUG') || TABMANAGER_DEBUG !== true) {
    http_response_code(403);
    ...
    exit;
}
```

**Implementation notes:** Fixed in `bin/bootstrap.php` for all three debug endpoints. 2026-05-25.

---

## HIGH

---

### SEC-03 — No tab ID validation — session flooding and arbitrary session keys ✅

**Files:** `bin/bootstrap.php`, `src/TabManager.php`  
**Severity:** High

**Vulnerability:**  
Tab IDs from HTTP input were used directly as `$_SESSION` array keys with no format or length validation. The `is_valid_uuid()` utility method existed but was never called.

**Impact:**  
Session flooding (arbitrary strings → unlimited session growth), unbounded key lengths, heartbeat amplification via `touchTab()` creating new entries for fake IDs.

**Mitigation applied:**  
Added `TabManager::isValidTabId(string $id): bool` as a public static method. Every endpoint in `bootstrap.php` now validates the tab ID against the UUID v4 pattern before calling any `TabManager` method. Invalid IDs are silently ignored (no-op response), consistent with existing null-ID behavior.

```php
// new static method in TabManager
public static function isValidTabId(string $id): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $id
    );
}

// bootstrap.php — applied to every endpoint
if ($tabId && TabManager::isValidTabId($tabId)) {
    $admin->indexNewTab($tabId);
}
```

**Implementation notes:** `isValidTabId()` added to `src/TabManager.php`. Validation applied to all five endpoints in `bin/bootstrap.php`. 2026-05-25.

---

### SEC-04 — `set()` creates session entries without prior registration ✅

**File:** `src/TabManager.php`  
**Severity:** High

**Vulnerability:**  
`set()` auto-vivified a new session entry if the tab was not previously registered, bypassing `indexNewTab()` and any future pre-registration hooks.

**Mitigation applied:**  
Added an `isset` check before writing. If the tab is not registered, `set()` returns silently — consistent with `markInactiveTab()` and `destroyTabSession()`.

```php
// after
public function set(string $key, mixed $value): void
{
    $tabId = $this->getTabId();
    if (!$tabId || !isset($_SESSION['tabmanager']['tabs'][$tabId])) return;
    ...
}
```

**Implementation notes:** Fixed in `src/TabManager.php`. 2026-05-25.

---

## MEDIUM

---

### SEC-05 — Cookie fallback allows tab impersonation via XSS ✅

**File:** `src/TabManager.php`  
**Severity:** Medium

**Vulnerability:**  
`getTabId()` fell back to the shared `TABMANAGER_TABID` cookie when the header was absent. A script with cookie-write access (XSS, subdomain injection) could control which tab's session slot is read/written.

**Mitigation applied:**  
Added `getTabIdStrict(): ?string` which returns only the `X-TabManager-TabId` header, with no cookie fallback. Documented in README and method docblock for use in sensitive contexts.

```php
public function getTabIdStrict(): ?string
{
    return $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
}
```

The existing `getTabId()` (with fallback) is preserved for non-AJAX page-load requests where the cookie is the only available identifier.

**Implementation notes:** `getTabIdStrict()` added to `src/TabManager.php`. README updated with usage guidance. 2026-05-25. `test_set_header_takes_priority_over_cookie` added 2026-06-07 — explicitly asserts header beats cookie when both are present, preventing silent regression of the core isolation guarantee.

---

### SEC-06 — `parse_url()` may return `null` — unhandled in bootstrap ✅

**File:** `bin/bootstrap.php`  
**Severity:** Medium

**Vulnerability:**  
`parse_url()` with `PHP_URL_PATH` returned `null` for malformed URIs. Passing `null` to `preg_match()` causes a `TypeError` in PHP 8.1+.

**Mitigation applied:**  
Result is cast to string with a null-coalescing fallback:

```php
// after
$uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
```

**Implementation notes:** Fixed in `bin/bootstrap.php`. 2026-05-25.

---

## LOW

---

### SEC-07 — 204 responses include a body — RFC 7230 violation ✅

**File:** `bin/bootstrap.php`  
**Severity:** Low

**Vulnerability:**  
`/tabmanager/new-tab` and `/tabmanager/tab-close` returned status 204 with a JSON body and `Content-Type` header, violating RFC 7230 §3.3.

**Mitigation applied:**  
Both endpoints changed to return 200 with the JSON body. The heartbeat endpoint already returned 204 without a body and was not changed.

```php
// after (new-tab and tab-close)
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
```

**Implementation notes:** Fixed in `bin/bootstrap.php`. 2026-05-25.

---

### SEC-08 — Session ID regeneration not documented ✅

**File:** `README.md`  
**Severity:** Low / Informational

**Vulnerability:**  
The package called `session_start()` without documenting that session ID regeneration on authentication events is the consuming application's responsibility. Risk of false sense of security.

**Mitigation applied:**  
Added a "Security notes" section to `README.md` covering:
- `TABMANAGER_DEBUG` must never be set in production
- The application must call `session_regenerate_id(true)` on login / privilege change
- `getTabIdStrict()` for sensitive endpoints

**Implementation notes:** README updated. 2026-05-25.

---

## Implementation log

| ID | Status | Date | Notes |
|----|--------|------|-------|
| SEC-01 | ✅ Implemented | 2026-05-25 | `debug_js` now exposes only `$_SESSION['tabmanager']` |
| SEC-02 | ✅ Implemented | 2026-05-25 | `REMOTE_ADDR` check removed from all 3 debug endpoints |
| SEC-03 | ✅ Implemented | 2026-05-25 | `isValidTabId()` added; validation in all 5 bootstrap endpoints |
| SEC-04 | ✅ Implemented | 2026-05-25 | `set()` checks tab registration before writing |
| SEC-05 | ✅ Implemented | 2026-05-25 | `getTabIdStrict()` added; documented in README |
| SEC-06 | ✅ Implemented | 2026-05-25 | `parse_url()` result cast to string with fallback |
| SEC-07 | ✅ Implemented | 2026-05-25 | `new-tab` and `tab-close` changed to 200 with body |
| SEC-08 | ✅ Implemented | 2026-05-25 | Security notes section added to README |
