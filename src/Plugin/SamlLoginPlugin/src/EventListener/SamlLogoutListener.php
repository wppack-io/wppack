<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Plugin\SamlLoginPlugin\EventListener;

use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;

/**
 * Initiates SAML SLO when a WordPress user logs out.
 */
final class SamlLogoutListener
{
    public function __construct(
        private readonly SamlLogoutHandler $logoutHandler,
        private readonly SamlSessionManager $sessionManager,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public function register(): void
    {
        add_action('wp_logout', [$this, 'onLogout'], 5);
    }

    public function onLogout(int $userId): void
    {
        $nameId = $this->sessionManager->getNameId($userId);

        if ($nameId === null) {
            return;
        }

        $sessionIndex = $this->sessionManager->getSessionIndex($userId);
        $this->sessionManager->clear($userId);

        // @codeCoverageIgnoreStart
        $this->logoutHandler->initiateLogout($nameId, $sessionIndex);
        // @codeCoverageIgnoreEnd
    }
}
