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
 * Handles /oauth/{provider}/authorize requests.
 *
 * Each provider gets its own controller instance bound to a specific
 * OAuthEntryPoint, eliminating the need for query-parameter-based dispatch.
 */
final class AuthorizeController
{
    public function __construct(
        private readonly OAuthEntryPoint $entryPoint,
        private readonly Request $request,
    ) {}

    public function __invoke(): Response
    {
        $returnTo = $this->request->query->getString('return_to');
        $returnTo = $returnTo !== '' ? wp_validate_redirect($returnTo, admin_url()) : null;

        $url = $this->entryPoint->getLoginUrl($returnTo);

        return new RedirectResponse($url);
    }
}
