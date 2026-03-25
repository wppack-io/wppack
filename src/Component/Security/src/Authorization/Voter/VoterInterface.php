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

interface VoterInterface
{
    public const ACCESS_GRANTED = 1;
    public const ACCESS_DENIED = -1;
    public const ACCESS_ABSTAIN = 0;

    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int;
}
