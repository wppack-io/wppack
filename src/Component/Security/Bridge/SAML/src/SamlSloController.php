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

use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\SAML\Session\SamlSessionManager;

final class SamlSloController
{
    public function __construct(
        private readonly SamlLogoutHandler $logoutHandler,
        private readonly SamlSessionManager $sessionManager,
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
    ) {}

    public function __invoke(): Response
    {
        if ($this->logoutHandler->isLogoutRequest($this->request)) {
            $this->clearSamlSession();
            $this->logoutHandler->handleIdpLogoutRequest($this->request);

            return new RedirectResponse($this->resolvePostLogoutRedirect());
        }

        if ($this->logoutHandler->isLogoutResponse($this->request)) {
            $this->clearSamlSession();
            $this->authSession->logout();

            return new RedirectResponse($this->resolvePostLogoutRedirect());
        }

        return new Response('', 400);
    }

    private function resolvePostLogoutRedirect(): string
    {
        $relayState = $this->request->query->get('RelayState');

        if ($relayState === null) {
            return home_url();
        }

        $parsed = parse_url($relayState);

        if (
            !isset($parsed['scheme'], $parsed['host'])
            || !in_array($parsed['scheme'], ['http', 'https'], true)
        ) {
            return home_url();
        }

        // Same host — always allowed
        $currentHost = parse_url(home_url(), \PHP_URL_HOST);

        if ($parsed['host'] === $currentHost) {
            return $relayState;
        }

        // Different host — only allowed for registered multisite domains
        if (is_multisite() && get_blog_id_from_url($parsed['host']) > 0) {
            return $relayState;
        }

        return home_url();
    }

    private function clearSamlSession(): void
    {
        $userId = $this->authSession->getCurrentUserId();
        if ($userId > 0) {
            $this->sessionManager->clear($userId);
        }
    }
}
