<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authorization\Voter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authorization\Voter\CapabilityVoter;
use WpPack\Component\Security\Authorization\Voter\VoterInterface;

final class CapabilityVoterTest extends TestCase
{
    private CapabilityVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new CapabilityVoter();
    }

    #[Test]
    public function abstainForRolePrefix(): void
    {
        $token = new NullToken();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, 'ROLE_ADMINISTRATOR'));
    }

    #[Test]
    public function deniedForUnauthenticatedToken(): void
    {
        $token = new NullToken();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'edit_posts'));
    }

    #[Test]
    public function grantedForUserWithCapability(): void
    {
        $userId = wp_create_user('cap_voter_test_user', 'password123', 'cap_voter_test@example.com');
        self::assertIsInt($userId);

        try {
            $user = new \WP_User($userId);
            $user->set_role('administrator');

            $token = new \WpPack\Component\Security\Authentication\Token\PostAuthenticationToken($user, $user->roles);

            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'manage_options'));
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function deniedForUserWithoutCapability(): void
    {
        $userId = wp_create_user('cap_voter_sub_user', 'password123', 'cap_voter_sub@example.com');
        self::assertIsInt($userId);

        try {
            $user = new \WP_User($userId);
            $user->set_role('subscriber');

            $token = new \WpPack\Component\Security\Authentication\Token\PostAuthenticationToken($user, $user->roles);

            self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'manage_options'));
        } finally {
            wp_delete_user($userId);
        }
    }
}
