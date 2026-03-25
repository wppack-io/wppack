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

final class AccessDecisionManager
{
    /** @param list<VoterInterface> $voters */
    public function __construct(
        private array $voters = [],
        private readonly bool $allowIfAllAbstain = false,
    ) {}

    public function addVoter(VoterInterface $voter): void
    {
        $this->voters[] = $voter;
    }

    public function decide(TokenInterface $token, string $attribute, mixed $subject = null): bool
    {
        $grant = 0;

        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $attribute, $subject);

            if ($result === VoterInterface::ACCESS_DENIED) {
                return false;
            }

            if ($result === VoterInterface::ACCESS_GRANTED) {
                ++$grant;
            }
        }

        if ($grant > 0) {
            return true;
        }

        return $this->allowIfAllAbstain;
    }
}
