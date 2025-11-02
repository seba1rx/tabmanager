# TabManager

**Package name:** `Seba1rx/TabManager`  

TabManager provides **per-tab session isolation** for PHP applications.  
It ensures each browser tab maintains its own independent session state, preventing shared session data between tabs, which is particularly useful for admin dashboards, forms, or multi-tab workflows.

---

## 🚀 Features

- 🔹 Isolates PHP session data per browser tab  
- 🔹 Works automatically through a lightweight JS client  
- 🔹 Exposes internal endpoints for managing tab lifecycle  
- 🔹 Includes a built-in debug interface (JSON or HTML)  
- 🔹 No dependencies — pure PHP + vanilla JS  

---

## 📁 Project Structure
```
/
├── src/
│ ├── TabManager.php # Core class
├── Exceptions/
│ └── Exceptions/TabManagerException.php
├── bin/
│ └── bootstrap.php # Registers HTTP endpoints
├── assets/
│ └── seba1rx_tabmanagerclient.js
├── demo/
│ └── index.php
│ └── addData.php
│ └── app.js
│ └── seba1rx-tabmanager.js
│ └── terminate.php
│ └── WordStringGenerator.php
```


## ⚙️ Installation

you can run

```bash
composer require seba1rx/tabmanager
```

or simply include the files in your project.  
If using Composer (recommended), autoload via PSR-4:

```json
{
  "autoload": {
    "psr-4": {
      "Seba1rx\\TabManager\\": "src/"
    }
  }
}
```
then run:

```bash
composer dump-autoload
```

---
🧩 How It Works

Each browser tab receives a unique UUIDv4 via the included JS client.
This UUID is stored in both:

window.sessionStorage (so it’s unique per tab)

A cookie (TABMANAGER_TABID) sent with each request

The PHP backend (TabManager class) reads this cookie to isolate $_SESSION data per tab.

🪄 Usage
1. Include the Bootstrap File

At the very start of your PHP app (e.g. in your entrypoint or index.php):

```php
require_once __DIR__ . '/vendor/autoload.php';
```
**Since it is bootstrapped in the autoload files you don't have to manually incude the bootstrap file, it is loaded automatically.**

This automatically registers these internal endpoints:

| Method | Endpoint                       | Description                          |
| :----- | :----------------------------- | :----------------------------------- |
| `POST` | `/tabmanager/new-tab`          | Index a new tab in session           |
| `POST` | `/tabmanager/tab-close`        | Mark tab as inactive                 |
| `GET`  | `/tabmanager/debug`            | List all tab sessions (JSON or HTML) |
| `POST` | `/tabmanager/debug/delete-tab` | Delete a tab session (debug only)    |


⚠️ Endpoints /debug_js, /debug_html and /debug/delete-tab are restricted to local addresses (127.0.0.1) or when SESSIONADMIN_DEBUG is defined as true.

2. Include the JS Client

Add this script in your main HTML file, typically inside <head> or right before </body>:

```html
<script src="/assets/seba1rx_tabmanagerclient.js"></script>
```

This script will:

Assign a UUID to the current tab

Set a **TABMANAGER_TABID** cookie

Notify the backend of new tabs or tab closures

3. Use the PHP API

You can now use TabManager in your PHP code:

```php
use Seba1rx\TabManager\TabManager;

session_start();

$tabManager = new TabManager();

// Set and get tab-specific session data
$tabManager->set('user_role', 'admin');
echo $tabManager->get('user_role'); // admin

// Debug
print_r($tabManager->debug());
```
---
🧰 Debug Interface

You can visualize all active and inactive tab sessions by visiting:
```bash
/tabmanager/debug_js
/tabmanager/debug_html
```

Be sure to set TABMANAGER_DEBUG in your js scope:

```js
<script>
    window.TABMANAGER_DEBUG = true;
</script>
```

Example:

```php
define('SESSIONADMIN_DEBUG', true);
require_once __DIR__ . '/bootstrap/tabmanager_bootstrap.php';
```

You’ll then see an HTML table showing:

* Tab UUIDs
* Active/inactive status
* Last active timestamp
* Keys stored in each tab
* Session size

Using debug_html: Each row includes a “Delete” button to clear that tab’s session data.

📦 Class Overview
TabManager

| Method                                             | Description                                            |
| :------------------------------------------------- | :----------------------------------------------------- |
| `indexNewTab(string $tabId)`                       | Registers a new tab in session                         |
| `set(string $key, mixed $value)`                   | Stores a value in the current tab’s session            |
| `get(string $key, mixed $default = null)`          | Retrieves a value from the current tab’s session       |
| `destroyTabSession(string $tabId)`                 | Deletes session data for a tab                         |
| `markInactiveTab(string $tabId)`                   | Marks tab as inactive (triggered by JS `beforeunload`) |
| `debug(): array`                                   | Returns a summary of all tab sessions                  |
| `uuid_v4(): string`                                | Generates a UUIDv4                                     |
| `is_valid_uuid(string $uuid, bool $onlyV4 = true)` | Validates UUID format                                  |


FAQ: 

**Q:** When using get and set methods wouldn't I need to provide the tab Uuid in order to get the data item from the right tab index?  
**A:** No, since the cookie carries the Uuid identifier, and then it is used to get the right data

---
🧠 Example Workflow

1. User opens your app in a new browser tab.
2. JS client assigns a UUID and sets the cookie.
3. JS client notifies /tabmanager/new-tab.
4. PHP backend indexes this new tab in the session.
5. Any calls to TabManager->set() or get() now isolate data to that tab only.
6. When the tab closes, JS notifies /tabmanager/tab-close, marking it inactive.

---
🛡️ Security Notes

* Each tab UUID is random and conforms to RFC 4122 (v4 format).
* Debug endpoints are restricted to local development by default.
* Data is stored only in $_SESSION; no external storage required.

---
🧾 License

MIT License © 2025 Seba1rx

---
💡 Tip

To integrate smoothly with existing PHP apps, place the bootstrap file before any output, and ensure session_start() has been called.