/**
 * TabManagerClient
 *
 * Assigns each browser tab a unique UUID and sends it to the PHP backend
 * via the X-TabManager-TabId request header on every AJAX call, enabling
 * per-tab session isolation. A shared cookie (TABMANAGER_TABID) is set as
 * a non-AJAX fallback only — the header is the primary mechanism.
 *
 * Composer copies this file to the consumer project root automatically.
 * Serve it from there or move it to your assets directory:
 * <script src="/seba1rx_tabmanagerclient.js"></script>
 */
const TabManagerClient = {
    /**
     * Tab Uuid
     * Tool to set an id to each tab.
     * This per-tab UUID mechanism lets you isolate session data and
     * prevents state bleed between tabs.
     *
     * Usage:
     * to assign the id to the tab just do:
     * * TabManagerClient.tab.assignTabUuid();
     *
     * if you ever need to get the id just do:
     * * TabManagerClient.tab.id;
     *
     * In your php backend you will be able to get this id on each request as:
     * * $_COOKIE['TABMANAGER_TABID']
     */
    tab: {
        /**
         * Tab unique identifier
         * @type {string|null}
         */
        id: null,

        /**
         * BroadcastChannel used to detect duplicate tabs and respond to ownership queries.
         * @type {BroadcastChannel|null}
         */
        _ownershipChannel: null,

        /**
         * Generates a UUID v4-like identifier
         * @returns {string}
         */
        generateUuid: () => {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * Reads or generates a candidate UUID from sessionStorage.
         * Does NOT confirm uniqueness — call resolveUniqueId() for that.
         */
        assignTabUuid: () => {
            let uid = window.sessionStorage.getItem('unique-tab-id');

            // Only generate a new UUID when sessionStorage has none.
            // window.name is NOT used as a condition: it can be empty after a
            // browser crash-restore or in certain privacy modes even when
            // sessionStorage still holds the correct UUID, which would cause
            // a spurious new UUID to be generated on reload.
            if (!uid) {
                uid = TabManagerClient.tab.generateUuid();
                window.sessionStorage.setItem('unique-tab-id', uid);
            }

            TabManagerClient.tab.id = uid;
            window.name = uid;
        },

        /**
         * Checks via BroadcastChannel whether another tab already owns candidateId.
         * If so, returns a fresh UUID. Otherwise returns candidateId unchanged.
         *
         * Handles duplicate tabs: when a tab is duplicated the browser copies
         * sessionStorage, so both tabs would share the same UUID. The original tab
         * is already running its ownership listener and will respond to the query,
         * causing the duplicate to generate a new UUID.
         *
         * Limitation: if the original tab is frozen (suspended by the browser after
         * prolonged inactivity) its JS is paused and cannot respond. In that case
         * the duplicate keeps the UUID. The heartbeat mechanism reduces the window
         * for this scenario by keeping last_active fresh on the server while the
         * original tab is visible.
         *
         * @param {string} candidateId
         * @returns {Promise<string>}
         */
        resolveUniqueId: (candidateId) => {
            return new Promise((resolve) => {
                if (!window.BroadcastChannel) {
                    resolve(candidateId);
                    return;
                }

                const channel = new BroadcastChannel('tabmanager-ownership');
                let claimed = false;

                channel.onmessage = (event) => {
                    const { type, tabId } = event.data ?? {};
                    if (type === 'ownership-ack' && tabId === candidateId) {
                        claimed = true;
                    }
                };

                channel.postMessage({ type: 'ownership-query', tabId: candidateId });

                setTimeout(() => {
                    channel.close();
                    if (claimed) {
                        resolve(TabManagerClient.tab.generateUuid());
                    } else {
                        resolve(candidateId);
                    }
                }, 80);
            });
        },

        /**
         * Opens a persistent BroadcastChannel that responds to ownership queries
         * from other tabs. Must be called after the tab's UUID is confirmed.
         */
        startOwnershipListener: () => {
            if (!window.BroadcastChannel) return;

            if (TabManagerClient.tab._ownershipChannel) {
                TabManagerClient.tab._ownershipChannel.close();
            }

            TabManagerClient.tab._ownershipChannel = new BroadcastChannel('tabmanager-ownership');
            TabManagerClient.tab._ownershipChannel.onmessage = (event) => {
                const { type, tabId } = event.data ?? {};
                if (type === 'ownership-query' && tabId === TabManagerClient.tab.id) {
                    TabManagerClient.tab._ownershipChannel.postMessage({
                        type: 'ownership-ack',
                        tabId: TabManagerClient.tab.id,
                    });
                }
            };
        },
    },

    /**
     * Periodic heartbeat that keeps last_active fresh on the server.
     *
     * Runs only while the tab is visible (Page Visibility API). Stops
     * automatically when the browser freezes or hides the tab, and resumes
     * with an immediate beat when the tab becomes visible again.
     *
     * The backend endpoint is configured via window.TABMANAGER_HEARTBEAT_URL
     * (useful for setups where /tabmanager/* routes are unavailable, e.g.
     * php -S without a router). Falls back to /tabmanager/heartbeat.
     *
     * Interval defaults to 30 s and can be overridden via
     * window.TABMANAGER_HEARTBEAT_INTERVAL (milliseconds).
     */
    heartbeat: {
        _timer: null,

        get url() {
            return window.TABMANAGER_HEARTBEAT_URL ?? '/tabmanager/heartbeat';
        },

        get intervalMs() {
            return window.TABMANAGER_HEARTBEAT_INTERVAL ?? 30000;
        },

        send: async () => {
            try {
                await fetch(TabManagerClient.heartbeat.url, {
                    method: 'POST',
                    headers: TabManagerClient.getHeaders(),
                });
            } catch (e) {
                if (window.TABMANAGER_DEBUG) {
                    console.warn('[TabManagerClient] Heartbeat failed:', e);
                }
            }
        },

        start: () => {
            if (TabManagerClient.heartbeat._timer) return;
            TabManagerClient.heartbeat._timer = setInterval(
                TabManagerClient.heartbeat.send,
                TabManagerClient.heartbeat.intervalMs
            );
        },

        stop: () => {
            if (TabManagerClient.heartbeat._timer) {
                clearInterval(TabManagerClient.heartbeat._timer);
                TabManagerClient.heartbeat._timer = null;
            }
        },
    },

    /**
     * Cookie utilities
     */
    cookie: {
        /**
         * Sets a cookie.
         * @param {string} name
         * @param {string} value
         * @param {number} days Expiration in days (optional)
         */
        set: (name, value, days = 1) => {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
        },

        /**
         * Reads a cookie value by name.
         * @param {string} name
         * @returns {string|null}
         */
        get: (name) => {
            const match = document.cookie.match(new RegExp('(^| )' + encodeURIComponent(name) + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }
    },

    /**
     * Returns headers that identify the current tab.
     * Include these in every fetch/XHR call so the PHP backend can isolate
     * session data per tab regardless of the shared cookie value.
     *
     * Usage:
     *   fetch('/my-endpoint', {
     *       method: 'POST',
     *       headers: { 'Content-Type': 'application/json', ...TabManagerClient.getHeaders() },
     *       body: JSON.stringify(payload),
     *   });
     *
     * @returns {Object}
     */
    getHeaders: () => ({
        'X-TabManager-TabId': TabManagerClient.tab.id,
    }),

    notifyTabClosed: () => {
        try {
            const url = window.TABMANAGER_TAB_CLOSE_URL ?? '/tabmanager/tab-close';
            const data = { tab_id: TabManagerClient.tab.id };
            navigator.sendBeacon(url, JSON.stringify(data));
        } catch (e) {
            console.warn('[TabManagerClient] Could not send tab close event:', e);
        }
    },

    notifyNewTab: () => {
        try {
            const url = window.TABMANAGER_NEW_TAB_URL ?? '/tabmanager/new-tab';
            const data = { tab_id: TabManagerClient.tab.id };
            navigator.sendBeacon(url, JSON.stringify(data));
        } catch (e) {
            console.warn('[TabManagerClient] Could not send new tab event:', e);
        }
    },

    /**
     * Checks whether the current tab is still indexed in the PHP session.
     *
     * Called on visibilitychange to visible, before resuming the heartbeat.
     * If the tab no longer exists (e.g. cleaned up by cleanupInactiveTabs()
     * while the tab was suspended), dispatches 'tabmanager:session-lost' on
     * document so the consuming app can warn the user.
     *
     * Fails open (returns true) on network error to avoid false positives.
     *
     * The backend endpoint is configurable via window.TABMANAGER_TAB_STATUS_URL
     * (useful for php -S setups without a router).
     */
    tabStatus: {
        get url() {
            return window.TABMANAGER_TAB_STATUS_URL ?? '/tabmanager/tab-status';
        },

        /**
         * @returns {Promise<boolean>} true if indexed, false if not found
         */
        check: async () => {
            try {
                const response = await fetch(TabManagerClient.tabStatus.url, {
                    method: 'GET',
                    headers: TabManagerClient.getHeaders(),
                });
                if (!response.ok) return true;
                const data = await response.json();
                return data.indexed === true;
            } catch (e) {
                if (window.TABMANAGER_DEBUG) {
                    console.warn('[TabManagerClient] Tab status check failed:', e);
                }
                return true;
            }
        },
    },

    /**
     * Initializes the tab manager client:
     * - Assigns tab UUID (resolving duplicates via BroadcastChannel)
     * - Sets the identifying cookie
     * - Registers ownership listener so future duplicate tabs can detect this one
     * - Starts the heartbeat while the tab is visible
     *
     * @returns {Promise<void>}
     */
    init: async () => {
        // Get or generate a candidate UUID from sessionStorage
        TabManagerClient.tab.assignTabUuid();
        const candidateId = TabManagerClient.tab.id;

        // Verify uniqueness: if another tab already owns this UUID (e.g. we are a
        // duplicate tab whose sessionStorage was copied from the original) a new
        // UUID is returned so each tab stays isolated.
        const confirmedId = await TabManagerClient.tab.resolveUniqueId(candidateId);

        if (confirmedId !== candidateId) {
            TabManagerClient.tab.id = confirmedId;
            window.sessionStorage.setItem('unique-tab-id', confirmedId);
            window.name = confirmedId;
        }

        // Start responding to ownership queries from future duplicate tabs
        TabManagerClient.tab.startOwnershipListener();

        const tabId = TabManagerClient.tab.id;
        const cookieName = 'TABMANAGER_TABID';
        const currentCookie = TabManagerClient.cookie.get(cookieName);

        if (currentCookie !== tabId) {
            TabManagerClient.cookie.set(cookieName, tabId);
        }

        // Notify backend to index the tab
        TabManagerClient.notifyNewTab();

        // Notify backend softly when tab is closing
        window.addEventListener('beforeunload', () => {
            TabManagerClient.heartbeat.stop();
            TabManagerClient.notifyTabClosed();
        });

        // Heartbeat: run only while the tab is visible.
        // On visibilitychange to visible (tab unfreeze or user switching back):
        // send an immediate beat then resume the interval.
        if (document.visibilityState === 'visible') {
            TabManagerClient.heartbeat.start();
        }

        // On visibility restore, verify the tab still exists before resuming
        // the heartbeat. If it was cleaned up server-side while suspended,
        // dispatch 'tabmanager:session-lost' so the app can warn the user.
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible') {
                const indexed = await TabManagerClient.tabStatus.check();
                if (!indexed) {
                    document.dispatchEvent(new CustomEvent('tabmanager:session-lost', {
                        detail: { tabId: TabManagerClient.tab.id },
                    }));
                    return;
                }
                TabManagerClient.heartbeat.send();
                TabManagerClient.heartbeat.start();
            } else {
                TabManagerClient.heartbeat.stop();
            }
        });

        if (window.TABMANAGER_DEBUG) {
            console.log(
                '[TabManagerClient] Tab UUID:', tabId,
                candidateId !== confirmedId ? '(reassigned — duplicate tab detected)' : ''
            );
        }
    },

    /**
     * Promise that resolves once init() has completed.
     * Await this before reading TabManagerClient.tab.id in consumer scripts.
     *
     * Example:
     *   await TabManagerClient.ready;
     *   const headers = TabManagerClient.getHeaders();
     *
     * @type {Promise<void>}
     */
    ready: null,
};

// Run automatically on DOMContentLoaded and expose the completion promise.
TabManagerClient.ready = new Promise((resolve) => {
    document.addEventListener('DOMContentLoaded', async () => {
        await TabManagerClient.init();
        resolve();
    });
});
