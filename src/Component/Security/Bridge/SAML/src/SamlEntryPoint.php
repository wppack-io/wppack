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

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

final class SamlEntryPoint
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
    ) {}

    /**
     * Register WordPress hooks for SSO-only configuration.
     *
     * login_init action redirects GET requests to IdP.
     * Allowed actions that bypass the redirect: logout, postpass.
     * The loggedout parameter redirects to home_url() to avoid re-authentication loop.
     */
    public function register(): void
    {
        add_filter('login_url', function (string $loginUrl, string $redirect): string {
            return $this->getLoginUrl($redirect !== '' ? $redirect : null);
        }, 10, 2);

        add_action('login_init', function (): void {
            // SSO-only: show error page instead of login form
            if ($this->request->query->has('saml_error')) {
                wp_die(
                    'SAML authentication failed. Please contact your administrator.',
                    'Authentication Error',
                    ['response' => 403, 'back_link' => false],
                );
            }

            // Without SLO, logout lands on ?loggedout=true at wp-login.php.
            // Redirecting to IdP would re-authenticate via live IdP session, so send to home_url().
            if ($this->request->query->has('loggedout')) {
                wp_safe_redirect(home_url());
                exit;
            }

            $action = $this->request->query->getString('action');

            if ($this->request->isMethod('GET')
                && $action !== 'logout'
                && $action !== 'postpass'
            ) {
                $redirectTo = $this->request->query->getString('redirect_to');
                $destination = $redirectTo !== ''
                    ? wp_validate_redirect($redirectTo, admin_url())
                    : admin_url();

                if ($this->authSession->isLoggedIn()) {
                    wp_safe_redirect($destination);
                    exit;
                }

                $this->start($destination);
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
