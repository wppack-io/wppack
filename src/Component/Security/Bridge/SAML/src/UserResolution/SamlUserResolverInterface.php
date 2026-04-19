<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\SAML\UserResolution;

interface SamlUserResolverInterface
{
    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User;
}
