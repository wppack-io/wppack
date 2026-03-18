<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Provider\WordPressUserProvider;
use WpPack\Component\Security\Exception\UserNotFoundException;

final class WordPressUserProviderTest extends TestCase
{
    private WordPressUserProvider $provider;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        $this->provider = new WordPressUserProvider();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUserIds as $userId) {
            wp_delete_user($userId);
        }

        $this->createdUserIds = [];
    }

    #[Test]
    public function loadUserByUsername(): void
    {
        $userId = wp_create_user('security_test_user', 'password123', 'security_test@example.com');
        self::assertIsInt($userId);
        $this->createdUserIds[] = $userId;

        $user = $this->provider->loadUserByIdentifier('security_test_user');

        self::assertSame($userId, $user->ID);
        self::assertSame('security_test_user', $user->user_login);
    }

    #[Test]
    public function loadUserByEmail(): void
    {
        $userId = wp_create_user('security_email_user', 'password123', 'security_email_test@example.com');
        self::assertIsInt($userId);
        $this->createdUserIds[] = $userId;

        $user = $this->provider->loadUserByIdentifier('security_email_test@example.com');

        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function loadNonExistentUserThrowsException(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider->loadUserByIdentifier('nonexistent_user_' . uniqid());
    }
}
