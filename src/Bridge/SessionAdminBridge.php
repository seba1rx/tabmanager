<?php

declare(strict_types=1);

namespace Seba1rx\TabManager\Bridge;

use Seba1rx\SessionAdmin\Contracts\TabHandlerInterface;
use Seba1rx\TabManager\Contracts\SessionStoreInterface;
use Seba1rx\TabManager\PhpSessionStore;
use Seba1rx\TabManager\TabManager;

/**
 * Bridge that makes TabManager compatible with seba1rx/sessionadmin.
 *
 * Extends TabManager and implements TabHandlerInterface so it can be
 * injected into SessionAdmin via setTabHandler(). Unlike TabManager,
 * this class does NOT call session_start() in its constructor — session
 * lifecycle is delegated entirely to SessionAdmin::activateSession().
 *
 * Usage:
 *
 *   use Seba1rx\TabManager\Bridge\SessionAdminBridge;
 *
 *   $session = new MySession();
 *   $session->setTabHandler(new SessionAdminBridge());
 *   $session->autoCleanupTabs = 30; // optional
 *   $session->activateSession();    // starts session, then runs cleanup
 *
 *   // Tab data is accessible through the handler after activateSession():
 *   $session->tabHandler->set('cart', $items);
 *   $cart = $session->tabHandler->get('cart');
 *
 * Requires seba1rx/sessionadmin to be installed:
 *
 *   composer require seba1rx/sessionadmin
 *
 * @see \Seba1rx\SessionAdmin\Contracts\TabHandlerInterface
 * @see \Seba1rx\TabManager\TabManager
 */
class SessionAdminBridge extends TabManager implements TabHandlerInterface
{
    /**
     * Initialises the store without starting the PHP session.
     *
     * SessionAdmin::activateSession() is responsible for calling session_start()
     * with the correct session name and cookie parameters. Starting the session
     * here would prevent SessionAdmin from applying its configuration.
     *
     * The tabmanager session key is initialised lazily: TabManager methods
     * all accept a missing 'tabmanager' key and handle it gracefully. The key
     * is created on the first indexNewTab() call (triggered by the JS client).
     *
     * @param SessionStoreInterface|null $store Custom session store, or null
     *   to use PhpSessionStore (recommended when using with SessionAdmin).
     */
    public function __construct(?SessionStoreInterface $store = null)
    {
        $this->store = $store ?? new PhpSessionStore();
        // Intentionally skip $this->store->start() — SessionAdmin owns the session.
    }
}
