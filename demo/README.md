# TabManager Demo

A minimal working example showing how to integrate `seba1rx/tabmanager` into a PHP application.

Open the same URL in multiple browser tabs, add data to each, and observe how each tab maintains its own isolated session slot. Duplicate a tab to see the duplicate-detection mechanism assign a new UUID automatically.

---

## Running the demo

```bash
cd demo
php -S localhost:8080
# open http://localhost:8080
```

**CLI session dump** (server must be running):

```bash
php demo/cli_dump.php
```

---

## File reference

### Integration files
These files map directly to the steps described in the main README. When integrating the package into a real app, these are the patterns to replicate.

| File | Role |
|------|------|
| `index.php` | **Step 2 & 3** — loads the JS client, sets `window.TABMANAGER_HEARTBEAT_URL`, shows how to configure the client before loading it |
| `app.js` | **Step 3 & 4** — awaits `TabManagerClient.ready`, spreads `getHeaders()` into every fetch call |
| `session.php` | **Step 5** — reads `X-TabManager-TabId` header, calls `indexNewTab()`, returns session as JSON |
| `heartbeat.php` | **Step 6** — reads `X-TabManager-TabId` header, calls `touchTab()`, responds 204 |
| `addData.php` | **Step 5** — calls `$tabManager->set()` to write tab-isolated data |

### Demo-only files
These support the demo but are not part of the integration pattern.

| File | Role |
|------|------|
| `bootstrap.php` | Redirects session storage to `demo/sessions/` so files stay local and readable by `cli_dump.php`. Not needed in a real app. |
| `debug.php` | Visual debug interface: shows all registered tabs, their status, `last_active` timestamp, stored key-value data, and a delete button per tab. Auto-refreshes every 3 s so you can watch heartbeats update `last_active` in real time. Also handles AJAX delete (POST to itself) as a workaround for `/tabmanager/debug/delete-tab` being unreachable without a router. In a real app use the built-in `/tabmanager/debug_html` and `/tabmanager/debug/delete-tab` endpoints instead. |
| `terminate.php` | Destroys the entire session and redirects home. Useful for resetting state during testing. |
| `cli_dump.php` | Reads session files directly from `demo/sessions/` and prints them as JSON. Useful for inspecting session state from the terminal without a browser. |
| `WordStringGenerator.php` | Generates random words for demo data. App logic, not related to TabManager. |
| `seba1rx_tabmanagerclient.js` | Copy of `assets/seba1rx_tabmanagerclient.js` placed here so `php -S` can serve it directly. In a real project Composer copies this to the project root automatically. |
| `sessions/` | Session file storage (gitignored, created on first run). |

---

## Key things to pay attention to

### 1. `window.TABMANAGER_HEARTBEAT_URL` in `index.php`

```html
<script>
    window.TABMANAGER_HEARTBEAT_URL = 'heartbeat.php';
</script>
```

This override exists because `php -S` serves files directly and does not route `/tabmanager/*` paths to PHP. In a real app with a front controller or a router that handles all requests, this override is not needed — the default `/tabmanager/heartbeat` endpoint works out of the box.

### 2. `await TabManagerClient.ready` in `app.js`

```js
document.addEventListener('DOMContentLoaded', async () => {
    await TabManagerClient.ready;
    // tab.id is confirmed here
});
```

`TabManagerClient.init()` is async. It waits ~80 ms for the `BroadcastChannel` duplicate-tab check before the UUID is final. Any code that reads `TabManagerClient.tab.id` or calls `getHeaders()` before `ready` resolves will receive `null`. This is the most common integration mistake.

### 3. `...TabManagerClient.getHeaders()` in every fetch call

```js
const response = await fetch('addData.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        ...TabManagerClient.getHeaders(), // sends X-TabManager-TabId
    },
});
```

This is what makes the backend know which tab is making the request. Without it, `$tabManager->set()` and `->get()` fall back to the shared cookie and all tabs write to the same session slot.

### 4. `indexNewTab()` is idempotent

```php
$tabManager->indexNewTab($tabId); // safe to call on every request
```

Calling this more than once for the same UUID has no effect. You can call it on every page load without risk of overwriting existing tab data.

### 5. The session structure

TabManager stores all data under a single key in `$_SESSION`:

```php
$_SESSION['tabmanager']['tabs']['<uuid>']['data']['your-key'] = 'your-value';
```

`$tabManager->set('key', 'value')` and `->get('key')` are shortcuts that resolve the current tab's UUID from the request header and read/write that slot. You never reference the UUID directly in your app code.

---

## What the demo does NOT show

- Integration with a PHP framework (Laravel, Symfony) — the session driver and bootstrap sequence differ per framework.
- **Custom session store** — the demo uses `new TabManager()` with no arguments, which defaults to `PhpSessionStore` (native PHP sessions). In applications where another package already manages the session, you can implement `Seba1rx\TabManager\Contracts\SessionStoreInterface` and pass it to the constructor: `new TabManager($myStore)`. See the main README for details.
- Server-side garbage collection of stale tabs — `last_active` is tracked; use `$tabManager->cleanupInactiveTabs($seconds)` to remove inactive tabs older than a given threshold.
- Multi-page navigation — the demo is a single page. In multi-page apps `TabManagerClient.ready` must be awaited on every page that uses the tab ID.
