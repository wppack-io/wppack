<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\UserResolution;

interface SamlUserResolverInterface
{
    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User;
}
