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

namespace WpPack\Component\Security\Bridge\SAML;

use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;

final class SamlSloController
{
    public function __construct(
        private readonly SamlLogoutHandler $logoutHandler,
        private readonly SamlSessionManager $sessionManager,
        private readonly Request $request,
    ) {}

    public function __invoke(): Response
    {
        if ($this->logoutHandler->isLogoutRequest()) {
            $this->clearSamlSession();
            $this->logoutHandler->handleIdpLogoutRequest($this->request);

            return new RedirectResponse(home_url());
        }

        if ($this->logoutHandler->isLogoutResponse()) {
            $this->clearSamlSession();
            wp_logout();

            return new RedirectResponse(home_url());
        }

        return new Response('', 400);
    }

    private function clearSamlSession(): void
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            $this->sessionManager->clear($userId);
        }
    }
}
