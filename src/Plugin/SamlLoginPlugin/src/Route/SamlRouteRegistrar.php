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

namespace WpPack\Plugin\SamlLoginPlugin\Route;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

final class SamlRouteRegistrar
{
    public function __construct(
        private readonly Request $request,
        private readonly SamlMetadataController $metadataController,
        private readonly SamlLogoutHandler $logoutHandler,
        private readonly AuthenticationManager $authenticationManager,
        private readonly string $metadataPath = '/saml/metadata',
        private readonly string $sloPath = '/saml/slo',
        private readonly string $acsPath = '/saml/acs',
    ) {}

    public function register(): void
    {
        add_action('template_redirect', [$this, 'handleRequest'], 1);
    }

    public function handleRequest(): void
    {
        $path = $this->request->getPathInfo();

        if ($path === $this->metadataPath && $this->request->isMethod('GET')) {
            $this->metadataController->serve();
        }

        if ($path === $this->sloPath) {
            $this->handleSlo();
        }

        if ($path === $this->acsPath && $this->request->isMethod('POST')) {
            $this->handleAcs();
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleSlo(): void
    {
        if ($this->logoutHandler->isLogoutRequest()) {
            $this->logoutHandler->handleIdpLogoutRequest($this->request);
            wp_safe_redirect(home_url());

            exit;
        }

        if ($this->logoutHandler->isLogoutResponse()) {
            wp_logout();
            wp_safe_redirect(home_url());

            exit;
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleAcs(): void
    {
        $result = $this->authenticationManager->handleAuthentication(null, '', '');

        if (!$result instanceof \WP_User) {
            wp_safe_redirect(wp_login_url() . '?action=saml_error');
        }

        exit;
    }
}
