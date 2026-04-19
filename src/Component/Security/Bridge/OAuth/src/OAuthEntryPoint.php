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

use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\Pkce\PkceGenerator;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WPPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WPPack\Component\Security\Bridge\OAuth\State\StoredState;

final class OAuthEntryPoint
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly OAuthConfiguration $configuration,
        private readonly OAuthStateStore $stateStore,
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
    ) {}

    /**
     * Start OAuth authorization flow.
     *
     * @return never
     */
    public function start(?string $returnTo = null): void
    {
        $url = $this->getLoginUrl($returnTo);

        wp_redirect($url);

        exit;
    }

    /**
     * Register WordPress hooks for SSO-only configuration.
     *
     * Replaces wp-login.php with IdP login:
     * - login_url filter: returns IdP authorization URL
     * - login_init action: redirects GET requests to IdP (skips ?action= for logout/lostpassword)
     */
    public function register(): void
    {
        add_filter('login_url', function (string $loginUrl, string $redirect): string {
            return $this->getLoginUrl($redirect !== '' ? $redirect : null);
        }, 10, 2);

        add_action('login_init', function (): void {
            // Interim-login (session expired modal): skip redirect, let WP show
            // the login form with SSO buttons opening in a new tab.
            if ($this->request->query->has('interim-login')) {
                return;
            }

            if ($this->request->isMethod('GET') && !$this->request->query->has('action')) {
                if ($this->authSession->isLoggedIn()) {
                    return;
                }

                $redirectTo = $this->request->query->getString('redirect_to');
                $returnTo = $redirectTo !== ''
                    ? wp_validate_redirect($redirectTo, admin_url())
                    : admin_url();
                $this->start($returnTo);
            }
        });
    }

    public function getLoginUrl(?string $returnTo = null): string
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $codeVerifier = null;
        $codeChallenge = null;

        if ($this->configuration->isPkceEnabled()) {
            $pkce = PkceGenerator::generate();
            $codeVerifier = $pkce['code_verifier'];
            $codeChallenge = $pkce['code_challenge'];
        }

        $storedState = StoredState::create($nonce, $codeVerifier, $returnTo);
        $this->stateStore->store($state, $storedState);

        return $this->provider->getAuthorizationUrl($state, $nonce, $codeChallenge);
    }
}
