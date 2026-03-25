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

        wp_logout();
        wp_clear_auth_cookie();
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
