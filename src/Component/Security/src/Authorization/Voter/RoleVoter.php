<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authorization\Voter;

use WpPack\Component\Security\Authentication\Token\TokenInterface;

final class RoleVoter implements VoterInterface
{
    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
    {
        if (!str_starts_with($attribute, 'ROLE_')) {
            return self::ACCESS_ABSTAIN;
        }

        if (!$token->isAuthenticated()) {
            return self::ACCESS_DENIED;
        }

        // Convert ROLE_ADMINISTRATOR -> administrator
        $role = strtolower(substr($attribute, 5));
        $userRoles = array_map('strtolower', $token->getRoles());

        return \in_array($role, $userRoles, true)
            ? self::ACCESS_GRANTED
            : self::ACCESS_DENIED;
    }
}
