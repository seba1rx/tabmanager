<?php
declare(strict_types=1);
/**
 * TabManager bootstrap
 *
 * Registers internal endpoints:
 *  - POST /tabmanager/new-tab
 *  - POST /tabmanager/heartbeat
 *  - POST /tabmanager/tab-close
 *  - GET  /tabmanager/tab-status
 *  - GET  /tabmanager/debug_js    (requires TABMANAGER_DEBUG=true)
 *  - GET  /tabmanager/debug_html  (requires TABMANAGER_DEBUG=true)
 *  - POST /tabmanager/debug/delete-tab (requires TABMANAGER_DEBUG=true)
 */

use Seba1rx\TabManager\TabManager;

if (!defined('__SEBA1RX_TABMANAGER_BOOTSTRAPPED__')) {
    define('__SEBA1RX_TABMANAGER_BOOTSTRAPPED__', true);

    // SEC-06: parse_url() returns null for malformed URIs; cast to string to
    // avoid passing null to preg_match() (TypeError in PHP 8.1+).
    $uri    = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // --- NEW TAB ENDPOINT ---------------------------------------------------
    if ($method === 'POST' && preg_match('~^/tabmanager/new-tab/?$~', $uri)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $tabId = $input['tab_id'] ?? null;

        // SEC-03: reject any tab_id that is not a valid UUID v4.
        if ($tabId && TabManager::isValidTabId($tabId)) {
            $admin = new TabManager();
            $admin->indexNewTab($tabId);
        }

        // SEC-07: 200 with body; 204 must not carry a response body (RFC 7230).
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // --- HEARTBEAT ENDPOINT -------------------------------------------------
    if ($method === 'POST' && preg_match('~^/tabmanager/heartbeat/?$~', $uri)) {
        $tabId = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;

        // SEC-03: validate UUID before touching the session.
        if ($tabId && TabManager::isValidTabId($tabId)) {
            $admin = new TabManager();
            $admin->touchTab($tabId);
        }

        http_response_code(204);
        exit;
    }

    // --- TAB CLOSE ENDPOINT -------------------------------------------------
    if ($method === 'POST' && preg_match('~^/tabmanager/tab-close/?$~', $uri)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $tabId = $input['tab_id'] ?? null;

        // SEC-03: validate UUID before touching the session.
        if ($tabId && TabManager::isValidTabId($tabId)) {
            $admin = new TabManager();
            $admin->markInactiveTab($tabId);
        }

        // SEC-07: 200 with body; 204 must not carry a response body (RFC 7230).
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // --- TAB STATUS ENDPOINT ------------------------------------------------
    if ($method === 'GET' && preg_match('~^/tabmanager/tab-status/?$~', $uri)) {
        $tabId   = $_SERVER['HTTP_X_TABMANAGER_TABID'] ?? null;
        $indexed = false;

        if ($tabId && TabManager::isValidTabId($tabId)) {
            $admin   = new TabManager();
            $indexed = $admin->isTabIndexed($tabId);
        }

        header('Content-Type: application/json');
        echo json_encode(['indexed' => $indexed, 'tab_id' => $tabId]);
        exit;
    }

    // --- DEBUG: DELETE TAB --------------------------------------------------
    if ($method === 'POST' && preg_match('~^/tabmanager/debug/delete-tab/?$~', $uri)) {

        // SEC-02: REMOTE_ADDR is always the proxy IP in reverse-proxy setups,
        // making localhost checks unreliable. TABMANAGER_DEBUG is the only
        // trustworthy gate — it must be explicitly set in application code
        // and must never be defined in production.
        if (!defined('TABMANAGER_DEBUG') || TABMANAGER_DEBUG !== true) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $tabId = $input['tab_id'] ?? null;

        // SEC-03: validate UUID before touching the session.
        if ($tabId && TabManager::isValidTabId($tabId)) {
            $tabManager = new TabManager();
            $tabManager->destroyTabSession($tabId);
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'deleted', 'tab_id' => $tabId]);
        exit;
    }

    // --- DEBUG: JSON DUMP ---------------------------------------------------
    if ($method === 'GET' && preg_match('~^/tabmanager/debug_js/?$~', $uri)) {

        // SEC-02: only TABMANAGER_DEBUG grants access; REMOTE_ADDR removed.
        if (!defined('TABMANAGER_DEBUG') || TABMANAGER_DEBUG !== true) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $tabManager = new TabManager();
        $tabs       = $tabManager->debug();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'package'             => 'seba1rx/tabmanager',
            'session_key'         => TabManager::SESSION_KEY,
            'tabs'                => $tabs,
            // SEC-01: only expose the tabmanager slice of $_SESSION, not the
            // full session (which may contain auth tokens, user data, etc.).
            'tabmanager_session'  => $_SESSION['tabmanager'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // --- DEBUG: HTML TABLE --------------------------------------------------
    if ($method === 'GET' && preg_match('~^/tabmanager/debug_html/?$~', $uri)) {

        // SEC-02: only TABMANAGER_DEBUG grants access; REMOTE_ADDR removed.
        if (!defined('TABMANAGER_DEBUG') || TABMANAGER_DEBUG !== true) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $tabManager = new TabManager();
        $tabs       = $tabManager->debug();

        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Tab Manager Debug</title>
            <style>
                body { font-family: system-ui, sans-serif; background: #f9f9f9; color: #333; padding: 2rem; }
                h1 { font-size: 1.6rem; margin-bottom: 1rem; }
                table { border-collapse: collapse; width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                th, td { padding: 0.7rem 1rem; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
                th { background: #fafafa; font-weight: 600; }
                tr:hover { background: #f5f5f5; }
                .active { color: #0a0; font-weight: bold; }
                .inactive { color: #a00; font-weight: bold; }
                .timestamp { color: #555; font-size: 0.9rem; }
                .keys { font-family: monospace; color: #444; font-size: 0.9rem; }
                .footer { margin-top: 1rem; font-size: 0.8rem; color: #777; }
                button { background: #e33; color: white; border: none; border-radius: 6px; padding: 0.4rem 0.7rem; cursor: pointer; transition: 0.2s; }
                button:hover { background: #c00; }
            </style>
        </head>
        <body>
            <h1>Tab Manager Debug</h1>
            <table>
                <thead>
                    <tr>
                        <th>Tab UUID</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Keys</th>
                        <th>Size (bytes)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tabs-body">
                <?php foreach ($tabs as $tabId => $info): ?>
                    <tr data-tab="<?= htmlspecialchars($tabId) ?>">
                        <td><code><?= htmlspecialchars($tabId) ?></code></td>
                        <td class="<?= $info['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $info['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td class="timestamp"><?= htmlspecialchars($info['last_active']) ?></td>
                        <td class="keys"><?= htmlspecialchars(implode(', ', $info['keys'])) ?: '—' ?></td>
                        <td><?= (int)$info['size'] ?></td>
                        <td><button onclick="deleteTab('<?= htmlspecialchars($tabId) ?>', this)">Delete</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="footer">
                <p><strong>Package:</strong> seba1rx/tabmanager</p>
                <p>Generated at <?= date('Y-m-d H:i:s') ?></p>
            </div>

            <script>
                async function deleteTab(tabId, btn) {
                    if (!confirm('Delete session data for this tab?')) return;

                    btn.disabled = true;
                    btn.textContent = 'Deleting...';

                    const response = await fetch('/tabmanager/debug/delete-tab', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tab_id: tabId })
                    });

                    if (response.ok) {
                        const row = btn.closest('tr');
                        row.style.background = '#fee';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        alert('Failed to delete tab.');
                        btn.disabled = false;
                        btn.textContent = 'Delete';
                    }
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
