# tabmanager — AI Implementation Skill

**Package:** `seba1rx/tabmanager`  
**Purpose:** Per-tab `$_SESSION` isolation in PHP. Each browser tab gets its own UUIDv4; session data is namespaced under that UUID so tabs cannot bleed state into each other.

---

## Mental model

```
Browser tab A                 Browser tab B
sessionStorage: uuid-A        sessionStorage: uuid-B
     |                              |
X-TabManager-TabId: uuid-A    X-TabManager-TabId: uuid-B
     |                              |
     v                              v
$_SESSION['tabmanager']['tabs']['uuid-A']['data']
$_SESSION['tabmanager']['tabs']['uuid-B']['data']
```

The UUID lives in `sessionStorage` (per-tab, not shared). Every AJAX request sends it as a request header. PHP reads that header to route reads/writes to the correct session slot.

---

## Data structure in `$_SESSION`

```php
$_SESSION['tabmanager']['tabs'] = [
    '<uuid-v4>' => [
        'data'        => [],      // key-value store — arbitrary types
        'is_active'   => true,    // false after beforeunload fires
        'last_active' => 1234567890,  // unix timestamp, updated by heartbeat
    ],
    // ...one entry per open tab
];
```

Other `$_SESSION` keys (auth, user data, etc.) are never touched.

---

## Installation

```bash
composer require seba1rx/tabmanager
```

Post-install script automatically copies `seba1rx_tabmanagerclient.js` to the project root. Serve it from there or copy it to your assets directory.

---

## Integration checklist

1. Include and configure the JS client in HTML
2. Await `TabManagerClient.ready` before using `tab.id`
3. Spread `TabManagerClient.getHeaders()` into every AJAX/fetch call
4. Call `new TabManager()` in PHP and use `set()`/`get()`
5. Ensure `/tabmanager/*` requests reach PHP (framework router note below)

---

## Step 1 — HTML: include the JS client

Place this **before** your application scripts. Set config globals **before** the script tag.

```html
<head>
    <script>
        // Required only if /tabmanager/* routes are unavailable (e.g. php -S without router):
        // window.TABMANAGER_HEARTBEAT_URL = '/heartbeat-tab.php';

        // Enable verbose JS logging in development:
        // window.TABMANAGER_DEBUG = true;
    </script>
    <script src="/seba1rx_tabmanagerclient.js"></script>
</head>
```

`init()` runs automatically on `DOMContentLoaded`. It:
- Reads or generates a UUID from `sessionStorage` key `unique-tab-id`
- Runs a `BroadcastChannel` ownership check (~80 ms) to detect duplicate tabs
- Sets cookie `TABMANAGER_TABID` (shared fallback — do not rely on it for isolation)
- POSTs to `/tabmanager/new-tab` to register the tab in PHP session
- Starts the heartbeat (30 s interval, pauses/resumes with Page Visibility API)
- Registers `beforeunload` to stop the heartbeat and POST to `/tabmanager/tab-close`

---

## Step 2 — JS: await `TabManagerClient.ready`

`init()` is async. Reading `tab.id` before it resolves returns `null`.

```js
document.addEventListener('DOMContentLoaded', async () => {
    await TabManagerClient.ready;   // UUID is now confirmed and unique

    console.log(TabManagerClient.tab.id);   // safe
    // safe to call getHeaders() from here
});
```

---

## Step 3 — JS: spread headers into every AJAX call

```js
const res = await fetch('/your-endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        ...TabManagerClient.getHeaders(),   // adds X-TabManager-TabId: <uuid>
    },
    body: JSON.stringify(payload),
});
```

**Calls that omit `getHeaders()` fall back to the shared cookie** — all tabs share the same cookie value, so tab isolation breaks silently for those calls.

---

## Step 4 — PHP: `set()` and `get()`

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';  // bootstrap runs here automatically

use Seba1rx\TabManager\TabManager;

$tm = new TabManager();

// Write to current tab's slot (reads X-TabManager-TabId header automatically)
$tm->set('step', 3);
$tm->set('draft_id', $postId);

// Read back
$step = $tm->get('step');           // 3
$missing = $tm->get('none', 'default');   // 'default'
```

`set()` is a no-op if the tab has not been registered via `indexNewTab()` — it never auto-creates a session slot from an arbitrary header value (security guard against session flooding).

---

## PHP class API

| Method | Signature | Behavior |
|--------|-----------|----------|
| `__construct` | `__construct()` | Starts session if not started; initializes `$_SESSION['tabmanager']` structure |
| `indexNewTab` | `indexNewTab(string $tabId): void` | Registers a tab; idempotent (second call is a no-op) |
| `touchTab` | `touchTab(string $tabId): void` | Updates `last_active` and sets `is_active = true`; creates the tab if absent |
| `set` | `set(string $key, mixed $value): void` | Writes to current tab's `data` slot; no-op if tab not registered |
| `get` | `get(string $key, mixed $default = null): mixed` | Reads from current tab's `data` slot; returns `$default` if absent |
| `destroyTabSession` | `destroyTabSession(string $tabId): void` | Removes the tab entry entirely; no-op if absent |
| `markInactiveTab` | `markInactiveTab(string $tabId): void` | Sets `is_active = false`; no-op if absent |
| `debug` | `debug(): array` | Returns summary of all tabs (`is_active`, `last_active` as `Y-m-d H:i:s`, `keys`, `size` bytes) |
| `getTabId` | `getTabId(): ?string` _(protected)_ | Reads `X-TabManager-TabId` header first, falls back to `TABMANAGER_TABID` cookie |
| `getTabIdStrict` | `getTabIdStrict(): ?string` | Header only — no cookie fallback. Use for sensitive endpoints |
| `isValidTabId` | `static isValidTabId(string $id): bool` | Validates UUID v4 format; used by bootstrap before any session write |

---

## How `set()` resolves the tab

```
set($key, $value)
  └─ getTabId()
       ├─ $_SERVER['HTTP_X_TABMANAGER_TABID']  ← primary (per-request, per-tab)
       └─ $_COOKIE['TABMANAGER_TABID']          ← fallback (shared, use only for non-AJAX)
```

If neither is present, `set()` does nothing. If a value is found but the tab is not registered, `set()` also does nothing.

---

## JS client API

| Member | Type | Description |
|--------|------|-------------|
| `TabManagerClient.ready` | `Promise<void>` | Resolves when `init()` completes. Always await before using `tab.id` |
| `TabManagerClient.tab.id` | `string\|null` | Confirmed UUID. `null` until `ready` resolves |
| `TabManagerClient.getHeaders()` | `() => object` | Returns `{ 'X-TabManager-TabId': <uuid> }`. Spread into every fetch call |
| `TabManagerClient.heartbeat.send()` | `async () => void` | Sends one heartbeat POST manually |
| `TabManagerClient.heartbeat.start()` | `() => void` | Starts the interval (idempotent) |
| `TabManagerClient.heartbeat.stop()` | `() => void` | Clears the interval |
| `TabManagerClient.tab.generateUuid()` | `() => string` | Generates a new UUIDv4 |

---

## JS configuration globals (set before the script tag)

| Variable | Default | Effect |
|----------|---------|--------|
| `window.TABMANAGER_DEBUG` | `false` | Enables `console.log` tracing in JS client |
| `window.TABMANAGER_HEARTBEAT_URL` | `'/tabmanager/heartbeat'` | Override heartbeat endpoint |
| `window.TABMANAGER_HEARTBEAT_INTERVAL` | `30000` | Heartbeat interval in milliseconds |

---

## HTTP endpoints (auto-registered by bootstrap)

| Method | Path | Called by | Description |
|--------|------|-----------|-------------|
| `POST` | `/tabmanager/new-tab` | JS `notifyNewTab()` | Registers tab; body: `{ "tab_id": "<uuid>" }` |
| `POST` | `/tabmanager/heartbeat` | JS `heartbeat.send()` | Touches tab; reads `X-TabManager-TabId` header |
| `POST` | `/tabmanager/tab-close` | JS `notifyTabClosed()` via `sendBeacon` | Marks tab inactive; body: `{ "tab_id": "<uuid>" }` |
| `GET` | `/tabmanager/debug_js` | Manual / dev tools | JSON dump of all tab sessions |
| `GET` | `/tabmanager/debug_html` | Manual / dev tools | HTML table of all tab sessions |
| `POST` | `/tabmanager/debug/delete-tab` | Debug UI | Destroys one tab's session entry |

Bootstrap intercepts matching URIs and calls `exit` — these requests never reach the application router.

---

## Framework / SPA router considerations

Bootstrap intercepts at the PHP level, before any framework router. This means:

- **Laravel / Symfony / Slim / plain PHP front-controller:** routes are intercepted automatically. No extra config.
- **SPA with its own JS router:** backend is still a PHP front-controller — works automatically.
- **`php -S` without a router script:** built-in server only routes to real files, so `/tabmanager/*` returns 404.

**`php -S` workaround** — create explicit PHP files and override the heartbeat URL:

```php
<?php // heartbeat-tab.php
require_once __DIR__ . '/vendor/autoload.php';
$tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
if ($tabId) {
    $tm = new Seba1rx\TabManager\TabManager();
    $tm->touchTab($tabId);
}
http_response_code(204);
```

```html
<script>window.TABMANAGER_HEARTBEAT_URL = '/heartbeat-tab.php';</script>
```

---

## Security rules (hard constraints)

### Never set `TABMANAGER_DEBUG` in production
```php
// DEV ONLY — exposes internal session structure
define('TABMANAGER_DEBUG', true);
require_once __DIR__ . '/vendor/autoload.php';
```
The constant is the only access gate for debug endpoints. There is no IP fallback.

### Use `getTabIdStrict()` for sensitive endpoints
For endpoints where tab impersonation via a forged cookie would cause harm:
```php
$tabId = $tm->getTabIdStrict();  // header only, no cookie fallback
if (!$tabId) { http_response_code(400); exit; }
```

### Session fixation is the application's responsibility
This package does not call `session_regenerate_id()`. The consuming app must call it on login and privilege escalation:
```php
session_regenerate_id(true);
```

### `set()` guards against implicit slot creation
If a request carries an arbitrary `X-TabManager-TabId` header for a tab that was never registered, `set()` silently does nothing. The attack vector (session flooding via forged headers) is blocked.

---

## Duplicate-tab detection

When a tab is duplicated the browser copies `sessionStorage`, causing both tabs to share the same UUID. On `init()` the JS client broadcasts an `ownership-query` on `BroadcastChannel('tabmanager-ownership')` and waits 80 ms. If the original tab responds with `ownership-ack`, the duplicate generates a fresh UUID.

**Known limitation:** if the original tab is frozen by the browser (Chrome Memory Saver, tab suspension) its JS is paused and cannot respond. The duplicate keeps the UUID. The heartbeat reduces the window where this matters by keeping `last_active` accurate while the original is visible, but client-side detection still fails for frozen tabs.

---

## Heartbeat lifecycle

```
tab visible     → heartbeat.start() (interval every 30 s)
tab hidden      → heartbeat.stop()
tab visible again → heartbeat.send() immediately + heartbeat.start()
beforeunload    → heartbeat.stop() + notifyTabClosed() via sendBeacon
```

Server side: `touchTab($tabId)` updates `last_active` and sets `is_active = true`. If the tab is not yet indexed, `touchTab` creates it via `indexNewTab`.

---

## Common implementation mistakes

| Mistake | Consequence | Fix |
|---------|-------------|-----|
| Reading `tab.id` before `await TabManagerClient.ready` | Gets `null`; requests fail silently | Always `await TabManagerClient.ready` first |
| Omitting `...TabManagerClient.getHeaders()` in a fetch | Cookie fallback used; all tabs share same slot | Always spread headers into every request |
| Calling `$tm->set()` before `indexNewTab()` | Silent no-op; data not stored | The JS client calls `/tabmanager/new-tab` automatically; for custom flows call `indexNewTab($tabId)` explicitly |
| Setting `TABMANAGER_DEBUG = true` in production | Debug endpoints exposed to any user | Remove the constant before deploy |
| Using page-load requests (no AJAX) for tab-isolated data | Cookie is shared — wrong tab may be identified | Only tab-isolate data from AJAX calls that include `getHeaders()` |
| `php -S` without a router and no custom heartbeat file | Heartbeat 404s; `last_active` stale | Set `window.TABMANAGER_HEARTBEAT_URL` to a physical PHP file |

---

## Minimal working example (new PHP project)

**`index.html`**
```html
<!DOCTYPE html>
<html>
<head>
    <script>window.TABMANAGER_DEBUG = true;</script>
    <script src="/seba1rx_tabmanagerclient.js"></script>
    <script src="/app.js" defer></script>
</head>
<body>
    <p>Tab: <span id="tab-id">…</span></p>
    <button id="save">Save data</button>
</body>
</html>
```

**`app.js`**
```js
document.addEventListener('DOMContentLoaded', async () => {
    await TabManagerClient.ready;

    document.getElementById('tab-id').textContent = TabManagerClient.tab.id;

    document.getElementById('save').addEventListener('click', async () => {
        await fetch('/save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...TabManagerClient.getHeaders(),
            },
            body: JSON.stringify({ value: 'hello from this tab' }),
        });
    });
});
```

**`save.php`**
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$tm = new Seba1rx\TabManager\TabManager();
$body = json_decode(file_get_contents('php://input'), true);

$tm->set('saved_value', $body['value'] ?? null);

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
```

---

## Testing (PHPUnit)

Unit tests use a null session save handler so no files are written:

```php
// tests/bootstrap.php
session_set_save_handler(
    open:    fn(string $path, string $name): bool => true,
    close:   fn(): bool => true,
    read:    fn(string $id): string => '',
    write:   fn(string $id, string $data): bool => true,
    destroy: fn(string $id): bool => true,
    gc:      fn(int $max_lifetime): int|false => 0,
);
session_start();
```

Each test resets global state in `setUp`:
```php
protected function setUp(): void
{
    $_SESSION = [];
    unset($_SERVER['HTTP_X_TABMANAGER_TABID']);
    $_COOKIE = [];
}
```

Run: `composer test`
