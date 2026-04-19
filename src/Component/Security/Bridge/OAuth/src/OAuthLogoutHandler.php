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

use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;

final class OAuthLogoutHandler
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly AuthenticationSession $authSession,
        private readonly ?string $redirectAfterLogout = null,
    ) {}

    /**
     * Initiate logout. If provider supports RP-Initiated Logout, redirect to IdP.
     * Otherwise, perform local logout only.
     *
     * @return string|null The IdP logout URL to redirect to, or null for local-only logout
     */
    public function initiateLogout(?string $idToken = null, ?string $returnTo = null): ?string
    {
        $this->handleLocalLogout();

        $endSessionEndpoint = $this->provider->getEndSessionEndpoint();

        if ($endSessionEndpoint !== null) {
            $params = [];

            if ($idToken !== null) {
                $params['id_token_hint'] = $idToken;
            }

            $redirect = $returnTo ?? $this->redirectAfterLogout;

            if ($redirect !== null) {
                $params['post_logout_redirect_uri'] = $redirect;
            }

            $url = $endSessionEndpoint;

            if ($params !== []) {
                $url .= '?' . http_build_query($params);
            }

            do_action(
                'wppack_oauth_logout',
                $this->authSession->getCurrentUserId(),
                true,
            );

            return $url;
        }

        do_action(
            'wppack_oauth_logout',
            $this->authSession->getCurrentUserId(),
            false,
        );

        return null;
    }

    public function handleLocalLogout(): void
    {
        $this->authSession->logout();
    }

    public function supportsRemoteLogout(): bool
    {
        return $this->provider->getEndSessionEndpoint() !== null;
    }
}
