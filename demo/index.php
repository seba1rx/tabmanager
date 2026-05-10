<?php
include __DIR__."/../vendor/autoload.php";
session_start();
$initialSession = json_encode($_SESSION, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TabManager — Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        window.TABMANAGER_DEBUG = true;
        window.TABMANAGER_AUTO_DESTROY = true;
    </script>
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
        <a href="/tabmanager/debug_html" target="_blank" class="btn btn-sm btn-outline-light">
            Debug view
        </a>
    </div>
</nav>

<div class="container py-4" style="max-width: 760px;">

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <div class="text-muted small mb-1">Current tab UUID</div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
