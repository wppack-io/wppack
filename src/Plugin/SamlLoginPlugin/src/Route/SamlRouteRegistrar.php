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

use WpPack\Component\Security\Bridge\SAML\SamlLogoutHandler;
use WpPack\Component\Security\Bridge\SAML\SamlMetadataController;

final class SamlRouteRegistrar
{
    public function __construct(
        private readonly SamlMetadataController $metadataController,
        private readonly SamlLogoutHandler $logoutHandler,
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
        $path = $this->getRequestPath();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($path === $this->metadataPath && $method === 'GET') {
            $this->metadataController->serve();
        }

        if ($path === $this->sloPath) {
            $this->handleSlo();
        }

        if ($path === $this->acsPath && $method === 'POST') {
            $this->handleAcs();
        }
    }

    private function getRequestPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, \PHP_URL_PATH);

        return $path !== null && $path !== false ? $path : '/';
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleSlo(): void
    {
        if ($this->logoutHandler->isLogoutRequest()) {
            $this->logoutHandler->handleIdpLogoutRequest();
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
        wp_signon(['user_login' => '', 'user_password' => '', 'remember' => false]);

        exit;
    }
}
