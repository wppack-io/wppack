<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\OAuth;

use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;

/**
 * Handles /oauth/{provider}/verify — cross-site token verification.
 *
 * Delegates to AuthenticationManager which finds the matching
 * OAuthAuthenticator via supports() (Pattern 2: POST + _wppack_oauth_token).
 */
final class OAuthVerifyController
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

        // WP_Error (failed handler) and null (no handler matched) both
        // land on wp-login with an error code. Previously both paths
        // fell through to admin_url, which silently hid auth failures.
        return new RedirectResponse(wp_login_url() . '?oauth_error=1');
    }
}
