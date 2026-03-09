<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML;

use WpPack\Component\Security\Bridge\SAML\Factory\SamlAuthFactory;

final class SamlLogoutHandler
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly ?string $redirectAfterLogout = null,
    ) {}

    /**
     * @return never
     */
    public function initiateLogout(?string $nameId, ?string $sessionIndex, ?string $returnTo = null): void
    {
        $auth = $this->authFactory->create();
        $auth->logout(
            $returnTo ?? $this->redirectAfterLogout,
            [],
            $nameId,
            $sessionIndex,
        );
    }

    public function handleIdpLogoutRequest(): void
    {
        $auth = $this->authFactory->create();
        $auth->processSLO(keepLocalSession: true, stay: true);

        if (function_exists('wp_logout')) {
            wp_logout();
        }

        if (function_exists('wp_clear_auth_cookie')) {
            wp_clear_auth_cookie();
        }
    }

    public function isLogoutRequest(): bool
    {
        return isset($_GET['SAMLRequest']);
    }

    public function isLogoutResponse(): bool
    {
        return isset($_GET['SAMLResponse']);
    }
}
