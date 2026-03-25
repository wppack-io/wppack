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

namespace WpPack\Component\Security\Bridge\OAuth;

use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Pkce\PkceGenerator;
use WpPack\Component\Security\Bridge\OAuth\Provider\ProviderInterface;
use WpPack\Component\Security\Bridge\OAuth\State\OAuthStateStore;
use WpPack\Component\Security\Bridge\OAuth\State\StoredState;

final class OAuthEntryPoint
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly OAuthConfiguration $configuration,
        private readonly OAuthStateStore $stateStore,
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
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
                $this->start();
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
