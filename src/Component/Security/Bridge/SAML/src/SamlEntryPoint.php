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
     * login_init action redirects GET requests to IdP (skips ?action= for logout/lostpassword/error).
     */
    public function register(): void
    {
        add_action('login_init', function (): void {
            if ($_SERVER['REQUEST_METHOD'] === 'GET'
                && !isset($_GET['action'])
                && !isset($_GET['loggedout'])
            ) {
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
