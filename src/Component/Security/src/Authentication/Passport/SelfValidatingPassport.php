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

namespace WPPack\Component\Security\Authentication\Passport;

use WPPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;

/**
 * Used for authenticators where credentials are validated externally (e.g., OAuth, SAML).
 *
 * No CredentialsBadge is required since the external provider has already verified the user.
 */
class SelfValidatingPassport extends Passport
{
    /**
     * @param list<BadgeInterface> $badges
     */
    public function __construct(UserBadge $userBadge, array $badges = [])
    {
        parent::__construct($userBadge, null, $badges);
    }
}
