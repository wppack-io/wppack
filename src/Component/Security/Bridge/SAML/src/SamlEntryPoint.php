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
     * login_init action redirects GET requests to IdP (skips ?action= for logout/lostpassword/error).
     */
    public function register(): void
    {
        add_filter('login_url', function (string $loginUrl, string $redirect): string {
            return $this->getLoginUrl($redirect !== '' ? $redirect : null);
        }, 10, 2);

        add_action('login_init', function (): void {
            // SSO-only: show error page instead of login form
            if ($this->request->query->getString('action') === 'saml_error') {
                wp_die(
                    'SAML authentication failed. Please contact your administrator.',
                    'Authentication Error',
                    ['response' => 403, 'back_link' => false],
                );
            }

            if ($this->request->isMethod('GET')
                && !$this->request->query->has('action')
                && !$this->request->query->has('loggedout')
            ) {
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
