<?php

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
