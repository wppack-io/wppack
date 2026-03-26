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

        if ($result instanceof \WP_User) {
            return new RedirectResponse(admin_url());
        }

        return new RedirectResponse(wp_login_url() . '?action=saml_error');
    }
}
