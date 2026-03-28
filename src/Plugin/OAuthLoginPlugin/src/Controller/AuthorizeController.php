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

namespace WpPack\Plugin\OAuthLoginPlugin\Controller;

use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\OAuth\OAuthEntryPoint;

/**
 * Handles /oauth/authorize?provider=NAME requests.
 *
 * Resolves the provider name from the query string, looks up the
 * corresponding OAuthEntryPoint, and redirects to the IdP authorization URL.
 */
final class AuthorizeController
{
    /**
     * @param array<string, OAuthEntryPoint> $entryPoints Provider name => OAuthEntryPoint
     */
    public function __construct(
        private readonly array $entryPoints,
        private readonly Request $request,
    ) {}

    public function __invoke(): Response
    {
        $provider = $this->request->query->get('provider', '');
        $returnTo = $this->request->query->get('return_to');

        if ($provider === '' || !isset($this->entryPoints[$provider])) {
            return new RedirectResponse(wp_login_url() . '?oauth_error=1');
        }

        $entryPoint = $this->entryPoints[$provider];
        $url = $entryPoint->getLoginUrl($returnTo);

        return new RedirectResponse($url);
    }
}
