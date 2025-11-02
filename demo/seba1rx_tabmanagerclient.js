/**
 * TabManagerClient
 *
 * This script ensures that each browser tab has its own unique ID and
 * that this ID is also sent to the backend through a cookie.
 *
 * Move this file to your assets directory and include it in your main HTML file:
 * <script src="/assets/seba1rx_tabmanagerclient.js"></script>
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
         * Assigns or retrieves the tab UUID.
         * Called automatically on script load.
         */
        assignTabUuid: () => {
            let uid = window.sessionStorage.getItem('unique-tab-id');

            // Generate a new one if missing or window.name is not set
            if (!uid || !window.name) {
                uid = TabManagerClient.tab.generateUuid();
                window.sessionStorage.setItem('unique-tab-id', uid);
                window.name = uid;
            }

            // Sync both sources
            TabManagerClient.tab.id = uid;
            window.name = uid;
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
    notifyTabClosed: () => {
        try {
            const url = '/tabmanager/tab-close'; // endpoint in your backend (automatically bootstrapped)
            const data = { tab_id: TabManagerClient.tab.id };
            navigator.sendBeacon(url, JSON.stringify(data));
        } catch (e) {
            console.warn('[TabManagerClient] Could not send tab close event:', e);
        }
    },
    notifyNewTab: () => {
        try {
            const url = '/tabmanager/new-tab'; // endpoint in your backend (automatically bootstrapped)
            const data = { tab_id: TabManagerClient.tab.id };
            navigator.sendBeacon(url, JSON.stringify(data));
        } catch (e) {
            console.warn('[TabManagerClient] Could not send tab close event:', e);
        }
    },

    /**
     * Initializes the tab manager client:
     * - Assigns tab UUID
     * - Sets the identifying cookie
     */
    init: () => {
        TabManagerClient.tab.assignTabUuid();
        const tabId = TabManagerClient.tab.id;
        const cookieName = 'TABMANAGER_TABID';
        const currentCookie = TabManagerClient.cookie.get(cookieName);

        if (currentCookie !== tabId) {
            TabManagerClient.cookie.set(cookieName, tabId);
        }

        // notify backend to index the tab
        TabManagerClient.notifyNewTab(tabId);

        // Notify backend softly when tab is closing
        window.addEventListener('beforeunload', () => {
            TabManagerClient.notifyTabClosed();
        });

        // console.log('[TabManagerClient] Tab UUID:', tabId);
    }
};

// Run automatically on load
document.addEventListener('DOMContentLoaded', TabManagerClient.init);