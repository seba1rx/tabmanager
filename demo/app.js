function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderSessionData(session) {
    const currentTabId = TabManagerClient.tab.id;
    const tabs = session?.tabmanager?.tabs ?? {};
    const tabEntries = Object.entries(tabs);

    const countEl = document.getElementById('data-count');
    if (countEl) countEl.textContent = tabEntries.length;

    const container = document.getElementById('session_data');
    if (!container) return;

    if (tabEntries.length === 0) {
        container.innerHTML = `
            <p class="text-muted p-3 mb-0 empty-state">
                No data yet — click <strong>Add random data</strong> to populate this tab's session.
            </p>`;
        return;
    }

    // Current tab first, then others sorted by last_active desc
    tabEntries.sort(([aId, a], [bId, b]) => {
        if (aId === currentTabId) return -1;
        if (bId === currentTabId) return 1;
        return (b?.last_active ?? 0) - (a?.last_active ?? 0);
    });

    const sections = tabEntries.map(([tabId, tabInfo]) => {
        const isCurrent = tabId === currentTabId;
        const data = tabInfo?.data ?? {};
        const dataEntries = Object.entries(data);
        const isActive = tabInfo?.is_active ?? false;
        const lastActive = tabInfo?.last_active
            ? new Date(tabInfo.last_active * 1000).toLocaleTimeString()
            : '—';

        const statusBadge = isActive
            ? '<span class="badge bg-success">active</span>'
            : '<span class="badge bg-secondary">inactive</span>';

        const currentBadge = isCurrent
            ? '<span class="badge bg-primary">this tab</span>'
            : '';

        const rows = dataEntries.length === 0
            ? '<tr><td colspan="2" class="text-muted ps-3 py-2 small fst-italic">empty</td></tr>'
            : dataEntries.map(([key, value]) => `
                <tr>
                    <td class="key-cell ps-3">${escapeHtml(key)}</td>
                    <td class="value-cell">${escapeHtml(value)}</td>
                </tr>`).join('');

        return `
            <div class="border-bottom ${isCurrent ? 'bg-light' : ''}">
                <div class="px-3 pt-2 pb-1 d-flex align-items-center gap-2 flex-wrap">
                    <code class="small text-muted">${escapeHtml(tabId)}</code>
                    ${currentBadge}
                    ${statusBadge}
                    <span class="text-muted small ms-auto">last active: ${lastActive}</span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }).join('');

    container.innerHTML = sections;
}

async function addData() {
    const btn = document.getElementById('btn-add');
    const spinner = document.getElementById('btn-spinner');
    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
        const response = await fetch('addData.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...TabManagerClient.getHeaders() }
        });

        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

        const result = await response.json();
        renderSessionData(result);
    } catch (error) {
        console.error('Error sending data:', error);
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
}

function reset() {
    window.location.href = 'terminate.php';
}

document.addEventListener('DOMContentLoaded', () => {
    const tabIdEl = document.getElementById('tabid');
    if (tabIdEl) tabIdEl.textContent = TabManagerClient.tab.id ?? '—';
    renderSessionData(window.__INITIAL_SESSION__);
});
