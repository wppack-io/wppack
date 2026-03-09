<?php

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
