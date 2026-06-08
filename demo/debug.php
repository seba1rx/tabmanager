<?php
/**
 * debug.php — Visual debug interface for the demo
 *
 * Shows all tabs currently registered in $_SESSION, their status, last_active
 * timestamp, stored key-value data, and a delete button per tab.
 *
 * This file exists because the built-in /tabmanager/debug_html endpoint is
 * not reachable with `php -S` (no router). It replicates the same interface
 * using a directly accessible PHP file.
 *
 * It also handles the AJAX delete action (POST to itself) since
 * /tabmanager/debug/delete-tab is similarly unreachable in this setup.
 *
 * In a real app with a proper router you would use the built-in endpoints:
 *   GET  /tabmanager/debug_html
 *   POST /tabmanager/debug/delete-tab
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// --- Handle AJAX delete request -------------------------------------------
// The JS on this page POSTs JSON here with { action: 'delete', tab_id: '...' }
// instead of /tabmanager/debug/delete-tab (which 404s without a router).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $deleteTabId = $input['tab_id'] ?? '';
    if (($input['action'] ?? '') === 'delete'
        && !empty($deleteTabId)
        && Seba1rx\TabManager\TabManager::isValidTabId($deleteTabId)
    ) {
        $tabManager = new Seba1rx\TabManager\TabManager();
        $tabManager->destroyTabSession($deleteTabId);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'deleted', 'tab_id' => $deleteTabId]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// --- Build display data ----------------------------------------------------
$tabManager = new Seba1rx\TabManager\TabManager();
$debugSummary = $tabManager->debug(); // ['uuid' => ['is_active', 'last_active', 'keys', 'size']]
$rawTabs = $_SESSION['tabmanager']['tabs'] ?? []; // full data including values
$now = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TabManager — Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .uuid { font-family: monospace; font-size: .85rem; }
        .data-key { font-family: monospace; color: #6c757d; font-size: .85rem; }
        .age { font-size: .8rem; color: #999; }
        .card { border: none; }
        .tab-row { transition: background .3s; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <span class="navbar-brand mb-0">TabManager <span style="font-size:.75rem;opacity:.6;font-weight:400">debug</span></span>
        <a href="index.php" class="btn btn-sm btn-outline-light">← Demo</a>
    </div>
</nav>

<div class="container" style="max-width: 900px;">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <span class="fw-semibold">Session tabs</span>
            <span class="badge bg-secondary ms-2"><?= count($rawTabs) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <label class="form-check-label text-muted small" for="auto-refresh">
                Auto-refresh
            </label>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">Refresh now</button>
        </div>
    </div>

    <?php if (empty($rawTabs)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-muted">No tabs registered in this session yet.</div>
        </div>
    <?php else: ?>

        <?php foreach ($rawTabs as $tabId => $tabData):
            $summary   = $debugSummary[$tabId] ?? [];
            $isActive  = $tabData['is_active'] ?? false;
            $lastActive = $tabData['last_active'] ?? 0;
            $ageSeconds = $now - $lastActive;
            $dataEntries = $tabData['data'] ?? [];

            if ($ageSeconds < 60) {
                $ageLabel = $ageSeconds . 's ago';
            } elseif ($ageSeconds < 3600) {
                $ageLabel = floor($ageSeconds / 60) . 'm ago';
            } else {
                $ageLabel = floor($ageSeconds / 3600) . 'h ago';
            }
        ?>
        <div class="card shadow-sm mb-3 tab-row" data-tab="<?= htmlspecialchars($tabId) ?>">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-2 flex-wrap">
                <code class="uuid text-muted"><?= htmlspecialchars($tabId) ?></code>

                <?php if ($isActive): ?>
                    <span class="badge bg-success">active</span>
                <?php else: ?>
                    <span class="badge bg-secondary">inactive</span>
                <?php endif; ?>

                <span class="age ms-1">
                    last active: <?= htmlspecialchars(date('H:i:s', $lastActive)) ?>
                    (<?= htmlspecialchars($ageLabel) ?>)
                </span>

                <button
                    class="btn btn-sm btn-outline-danger ms-auto"
                    onclick="deleteTab('<?= htmlspecialchars($tabId) ?>', this)"
                >
                    Delete
                </button>
            </div>

            <?php if (empty($dataEntries)): ?>
                <div class="card-body text-muted small fst-italic py-2">No data stored for this tab.</div>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Key</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataEntries as $key => $value): ?>
                        <tr>
                            <td class="data-key ps-3"><?= htmlspecialchars((string) $key) ?></td>
                            <td><?= htmlspecialchars((string) $value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <p class="text-muted small mt-3">
        Generated at <?= date('Y-m-d H:i:s') ?> &mdash; seba1rx/tabmanager
    </p>
</div>

<script>
    // Auto-refresh every 3 seconds while the toggle is on.
    // Lets you watch last_active update as heartbeats arrive.
    const toggle = document.getElementById('auto-refresh');
    const timer  = setInterval(() => {
        if (toggle.checked) location.reload();
    }, 10000);

    // Delete a tab via AJAX (POST to this same file instead of
    // /tabmanager/debug/delete-tab, which is unreachable without a router).
    async function deleteTab(tabId, btn) {
        clearInterval(timer); // stop auto-refresh so it can't fire during confirm

        if (!confirm('Delete session data for this tab?')) {
            location.reload(); // restart timer via fresh page load
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Deleting…';

        const response = await fetch('debug.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'delete', tab_id: tabId }),
        });

        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to delete tab.');
            location.reload();
        }
    }
</script>

</body>
</html>
