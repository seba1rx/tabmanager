<?php
/**
 * INTEGRATION STEP: include the autoloader.
 * bootstrap.php (registered under Composer "files" autoload) runs automatically
 * here and registers all /tabmanager/* endpoints. No extra configuration needed.
 */
require __DIR__ . '/bootstrap.php';        // demo-only: redirects session storage to demo/sessions/
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

// Pre-load the current session state so the page can render without a second AJAX call.
$initialSession = json_encode($_SESSION, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TabManager — Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--
        INTEGRATION STEP: configure the JS client before loading it.

        The TABMANAGER_*_URL globals override the default /tabmanager/* endpoints.
        Those defaults work in any setup where requests are routed through a PHP
        front controller (frameworks, SPAs with a catch-all router).

        This demo runs with `php -S` which serves files directly without a router,
        so /tabmanager/* paths return 404. Physical PHP files are used as workarounds.
    -->
    <script>
        window.TABMANAGER_DEBUG           = true;
        window.TABMANAGER_HEARTBEAT_URL   = 'heartbeat.php';
        window.TABMANAGER_TAB_STATUS_URL  = 'tab_status.php';
        window.TABMANAGER_TAB_CLOSE_URL   = 'tab_close.php';
    </script>

    <!--
        INTEGRATION STEP: load the JS client.
        It runs automatically on DOMContentLoaded:
          1. Reads or generates a UUID from sessionStorage
          2. Checks via BroadcastChannel that no other tab owns the UUID (duplicate detection)
          3. Sets the TABMANAGER_TABID cookie as fallback
          4. Notifies the backend of the new tab
          5. Starts the heartbeat while the tab is visible
    -->
    <script src="seba1rx_tabmanagerclient.js"></script>

    <style>
        body { background: #f0f2f5; }
        .navbar-brand span.pkg { font-size: .75rem; opacity: .6; font-weight: 400; }
        .tab-uuid { font-family: monospace; letter-spacing: .03em; font-size: .95rem; }
        #session_data .empty-state { color: #aaa; }
        .key-cell { font-family: monospace; font-size: .85rem; color: #6c757d; }
        .value-cell { font-weight: 500; }
        .card { border: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <span class="navbar-brand mb-0">
            TabManager
            <span class="pkg ms-2">seba1rx/tabmanager</span>
        </span>
        <a href="debug.php" target="_blank" class="btn btn-sm btn-outline-light">
            Debug view
        </a>
    </div>
</nav>

<div class="container py-4" style="max-width: 760px;">

    <!--
        INTEGRATION: listen for 'tabmanager:session-lost' to warn the user
        when the tab's session data was removed server-side while it was suspended.
        The event fires on visibilitychange to visible if the tab is no longer indexed.
        Replace this with your own UI — modal, toast, redirect, etc.
    -->
    <div id="session-lost-alert" class="alert alert-warning alert-dismissible d-none mb-3" role="alert">
        <strong>Session data lost.</strong>
        This tab was inactive for too long and its session data was removed by the server.
        Reload the page or start a new session.
        <button type="button" class="btn-close" onclick="this.closest('.alert').classList.add('d-none')"></button>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <div class="text-muted small mb-1">Current tab UUID</div>
                    <!-- Populated by app.js after TabManagerClient.ready resolves -->
                    <span id="tabid" class="tab-uuid text-primary">—</span>
                </div>
                <span id="tab-status" class="badge bg-success ms-auto">active</span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-3">
        <button id="btn-add" class="btn btn-primary" onclick="addData()">
            <span id="btn-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
            Add random data
        </button>
        <button class="btn btn-outline-danger" onclick="reset()">Reset session</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
            <span class="fw-semibold">Session data <span class="text-muted fw-normal small">(all tabs)</span></span>
            <span id="data-count" class="badge bg-secondary rounded-pill">0</span>
        </div>
        <div id="session_data" class="card-body p-0">
            <p class="text-muted p-3 mb-0 empty-state">
                No data yet — click <strong>Add random data</strong> to populate this tab's session.
            </p>
        </div>
    </div>

</div>

<script>window.__INITIAL_SESSION__ = <?= $initialSession ?>;</script>
<script src="app.js"></script>
<script>
    document.addEventListener('tabmanager:session-lost', () => {
        document.getElementById('session-lost-alert').classList.remove('d-none');
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
