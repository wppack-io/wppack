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

final class SamlLogoutHandler
{
    public function __construct(
        private readonly SamlAuthFactory $authFactory,
        private readonly AuthenticationSession $authSession,
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

    public function handleIdpLogoutRequest(Request $request): void
    {
        $auth = $this->authFactory->create();

        // onelogin/php-saml reads $_GET directly. WordPress's wp_magic_quotes()
        // has already applied addslashes() to $_GET, corrupting encoded data.
        // Temporarily replace $_GET with the clean (wp_unslash'd) Request data.
        $originalGet = $_GET;
        $_GET = $request->query->all();

        try {
            $auth->processSLO(keepLocalSession: true, stay: true);
        } finally {
            $_GET = $originalGet;
        }

        $this->authSession->logout();
    }

    public function isLogoutRequest(Request $request): bool
    {
        return $request->query->has('SAMLRequest');
    }

    public function isLogoutResponse(Request $request): bool
    {
        return $request->query->has('SAMLResponse');
    }
}
