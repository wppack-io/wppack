<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\UserResolution;

interface OAuthUserResolverInterface
{
    /**
     * Resolve a WordPress user from OAuth subject identifier and claims.
     *
     * @param string $subject The subject identifier (sub claim)
     * @param array<string, mixed> $claims All available claims from ID token or userinfo
     * @return \WP_User The resolved WordPress user
     * @throws \WpPack\Component\Security\Exception\AuthenticationException If user cannot be resolved
     */
    public function resolveUser(string $subject, array $claims): \WP_User;
}
