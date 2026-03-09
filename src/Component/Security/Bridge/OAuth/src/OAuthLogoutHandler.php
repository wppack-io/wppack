<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth;

use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;

final class OAuthLogoutHandler
{
    public function __construct(
        private readonly ProviderInterface $provider,
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

            if (function_exists('do_action')) {
                do_action(
                    'wppack_oauth_logout',
                    function_exists('get_current_user_id') ? get_current_user_id() : 0,
                    true,
                );
            }

            return $url;
        }

        if (function_exists('do_action')) {
            do_action(
                'wppack_oauth_logout',
                function_exists('get_current_user_id') ? get_current_user_id() : 0,
                false,
            );
        }

        return null;
    }

    public function handleLocalLogout(): void
    {
        if (function_exists('wp_logout')) {
            wp_logout();
        }

        if (function_exists('wp_clear_auth_cookie')) {
            wp_clear_auth_cookie();
        }
    }

    public function supportsRemoteLogout(): bool
    {
        return $this->provider->getEndSessionEndpoint() !== null;
    }
}
