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

use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

final class SamlEntryPoint
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
    ) {}

    /**
     * Register WordPress hooks for SSO-only configuration.
     *
     * Replaces wp-login.php with IdP login:
     * - login_url filter: returns IdP SSO URL
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

    /**
     * @return never
     */
    public function start(?string $returnTo = null): void
    {
        $auth = $this->authFactory->create();
        $auth->login($returnTo);
    }

    public function getLoginUrl(?string $returnTo = null): string
    {
        $auth = $this->authFactory->create();

        return $auth->login($returnTo, [], false, false, true);
    }
}
