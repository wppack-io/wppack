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

namespace WpPack\Component\Security\Bridge\SAML\Factory;

use OneLogin\Saml2\Auth;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

class SamlAuthFactory
{
    public function __construct(
        private readonly SamlConfiguration $configuration,
    ) {}

    public function create(): Auth
    {
        return new Auth($this->configuration->toOneLoginArray());
    }

    public function getConfiguration(): SamlConfiguration
    {
        return $this->configuration;
    }
}
