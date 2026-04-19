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

namespace WPPack\Component\Security\Tests\Authorization\Voter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Authentication\Token\NullToken;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WPPack\Component\Security\Authorization\Voter\VoterInterface;

final class AccessDecisionManagerTest extends TestCase
{
    #[Test]
    public function grantedWhenVoterGrants(): void
    {
        $voter = $this->createVoter(VoterInterface::ACCESS_GRANTED);
        $adm = new AccessDecisionManager([$voter]);

        self::assertTrue($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function deniedWhenVoterDenies(): void
    {
        $voter = $this->createVoter(VoterInterface::ACCESS_DENIED);
        $adm = new AccessDecisionManager([$voter]);

        self::assertFalse($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function denyOverridesGrant(): void
    {
        $grant = $this->createVoter(VoterInterface::ACCESS_GRANTED);
        $deny = $this->createVoter(VoterInterface::ACCESS_DENIED);
        $adm = new AccessDecisionManager([$grant, $deny]);

        self::assertFalse($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function allAbstainDeniedByDefault(): void
    {
        $abstain = $this->createVoter(VoterInterface::ACCESS_ABSTAIN);
        $adm = new AccessDecisionManager([$abstain]);

        self::assertFalse($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function allAbstainAllowedWhenConfigured(): void
    {
        $abstain = $this->createVoter(VoterInterface::ACCESS_ABSTAIN);
        $adm = new AccessDecisionManager([$abstain], allowIfAllAbstain: true);

        self::assertTrue($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function noVotersDefaultsDeny(): void
    {
        $adm = new AccessDecisionManager();

        self::assertFalse($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function addVoterAddsNewVoter(): void
    {
        $adm = new AccessDecisionManager();
        $voter = $this->createVoter(VoterInterface::ACCESS_GRANTED);

        $adm->addVoter($voter);

        self::assertTrue($adm->decide(new NullToken(), 'some_attribute'));
    }

    #[Test]
    public function decideWithSubjectPassesItToVoters(): void
    {
        $subjectReceived = null;
        $voter = new class ($subjectReceived) implements VoterInterface {
            public function __construct(private mixed &$subjectReceived) {}

            public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
            {
                $this->subjectReceived = $subject;

                return self::ACCESS_GRANTED;
            }
        };

        $adm = new AccessDecisionManager([$voter]);

        $adm->decide(new NullToken(), 'edit_post', 42);

        self::assertSame(42, $subjectReceived);
    }

    #[Test]
    public function noVotersAllowedWhenAllAbstainConfigured(): void
    {
        $adm = new AccessDecisionManager([], allowIfAllAbstain: true);

        self::assertTrue($adm->decide(new NullToken(), 'some_attribute'));
    }

    private function createVoter(int $result): VoterInterface
    {
        return new class ($result) implements VoterInterface {
            public function __construct(private readonly int $result) {}

            public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
            {
                return $this->result;
            }
        };
    }
}
