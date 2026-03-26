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

final class SamlSloController
{
    public function __construct(
        private readonly SamlLogoutHandler $logoutHandler,
        private readonly Request $request,
    ) {}

    public function __invoke(): Response
    {
        if ($this->logoutHandler->isLogoutRequest()) {
            $this->logoutHandler->handleIdpLogoutRequest($this->request);

            return new RedirectResponse(home_url());
        }

        if ($this->logoutHandler->isLogoutResponse()) {
            wp_logout();

            return new RedirectResponse(home_url());
        }

        return new Response('', 400);
    }
}
