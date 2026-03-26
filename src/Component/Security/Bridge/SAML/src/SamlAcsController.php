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
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;

final class SamlAcsController
{
    public function __construct(
        private readonly AuthenticationManagerInterface $authenticationManager,
    ) {}

    public function __invoke(): Response
    {
        $result = $this->authenticationManager->handleAuthentication(null, '', '');

        // AuthenticationManager already sent the redirect via Response::send()
        // for both success (RelayState URL) and failure (login error page).
        // Return empty 302 so RouteEntry exits without overriding Location header.
        if ($result instanceof \WP_User || $result instanceof \WP_Error) {
            return new Response('', 302);
        }

        // No authenticator matched — fallback
        return new RedirectResponse(site_url('wp-login.php', 'login') . '?saml_error=true');
    }
}
