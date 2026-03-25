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

    #[Test]
    public function grantedForSubjectBasedCapabilityCheck(): void
    {
        $authorId = wp_create_user('cap_voter_author_' . uniqid(), 'password123', 'cap_author_' . uniqid() . '@example.com');
        self::assertIsInt($authorId);

        try {
            $user = new \WP_User($authorId);
            $user->set_role('author');

            // Create a post by this author
            $postId = wp_insert_post([
                'post_title' => 'Test Post for Capability',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_author' => $authorId,
            ]);

            self::assertIsInt($postId);

            $token = new \WpPack\Component\Security\Authentication\Token\PostAuthenticationToken($user, $user->roles);

            // Author should be able to edit their own post
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'edit_post', $postId));

            wp_delete_post($postId, true);
        } finally {
            wp_delete_user($authorId);
        }
    }

    #[Test]
    public function deniedForSubjectBasedCapabilityCheckOnOthersPost(): void
    {
        $subscriberId = wp_create_user('cap_voter_subscriber_' . uniqid(), 'password123', 'cap_sub_' . uniqid() . '@example.com');
        self::assertIsInt($subscriberId);

        $authorId = wp_create_user('cap_voter_other_author_' . uniqid(), 'password123', 'cap_other_' . uniqid() . '@example.com');
        self::assertIsInt($authorId);

        try {
            $subscriber = new \WP_User($subscriberId);
            $subscriber->set_role('subscriber');

            $postId = wp_insert_post([
                'post_title' => 'Other Author Post',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_author' => $authorId,
            ]);

            self::assertIsInt($postId);

            $token = new \WpPack\Component\Security\Authentication\Token\PostAuthenticationToken($subscriber, $subscriber->roles);

            // Subscriber should NOT be able to edit another user's post
            self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'edit_post', $postId));

            wp_delete_post($postId, true);
        } finally {
            wp_delete_user($subscriberId);
            wp_delete_user($authorId);
        }
    }
}
