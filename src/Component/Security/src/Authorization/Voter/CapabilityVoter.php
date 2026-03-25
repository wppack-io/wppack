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

namespace WpPack\Component\Security\Authorization\Voter;

use WpPack\Component\Security\Authentication\Token\TokenInterface;

final class CapabilityVoter implements VoterInterface
{
    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
    {
        // Let RoleVoter handle role checks
        if (str_starts_with($attribute, 'ROLE_')) {
            return self::ACCESS_ABSTAIN;
        }

        if (!$token->isAuthenticated()) {
            return self::ACCESS_DENIED;
        }

        $user = $token->getUser();

        // Use user_can() for subject-based checks, otherwise check capability directly
        if ($subject !== null) {
            $granted = user_can($user, $attribute, $subject);
        } else {
            $granted = user_can($user, $attribute);
        }

        return $granted ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
    }
}
