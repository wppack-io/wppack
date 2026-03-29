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

namespace WpPack\Plugin\OAuthLoginPlugin\Controller;

use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;

/**
 * Handles /oauth/verify — cross-site token verification.
 *
 * Delegates to AuthenticationManager which finds the matching
 * OAuthAuthenticator via supports() (Pattern 2: POST + _wppack_oauth_token).
 */
final class VerifyController
{
    public function __construct(
        private readonly AuthenticationManagerInterface $authenticationManager,
    ) {}

    public function __invoke(): Response
    {
        $result = $this->authenticationManager->handleAuthentication(null, '', '');

        if ($result instanceof \WP_User || $result instanceof \WP_Error) {
            return new RedirectResponse(admin_url());
        }

        return new RedirectResponse(wp_login_url() . '?oauth_error=1');
    }
}
