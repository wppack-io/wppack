<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\UserDataCollector;

final class UserDataCollectorTest extends TestCase
{
    private UserDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new UserDataCollector();
    }

    #[Test]
    public function getNameReturnsUser(): void
    {
        self::assertSame('user', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsUser(): void
    {
        self::assertSame('User', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('is_user_logged_in')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_logged_in']);
        self::assertSame(0, $data['user_id']);
        self::assertSame('', $data['username']);
        self::assertSame('', $data['display_name']);
        self::assertSame('', $data['email']);
        self::assertSame([], $data['roles']);
        self::assertSame([], $data['capabilities']);
        self::assertFalse($data['is_super_admin']);
        self::assertSame('none', $data['authentication']);
    }

    #[Test]
    public function getBadgeValueReturnsAnonWhenNotLoggedIn(): void
    {
        if (function_exists('is_user_logged_in')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();

        self::assertSame('anon.', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefaultWhenNotLoggedIn(): void
    {
        if (function_exists('is_user_logged_in')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();

        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function maskEmailShowsOnlyDomain(): void
    {
        self::assertSame('***@example.com', $this->collector->maskEmail('user@example.com'));
    }

    #[Test]
    public function maskEmailHandlesInvalidEmail(): void
    {
        self::assertSame('***', $this->collector->maskEmail('invalid-email'));
    }

    #[Test]
    public function maskEmailHandlesSubdomain(): void
    {
        self::assertSame('***@mail.example.co.jp', $this->collector->maskEmail('admin@mail.example.co.jp'));
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
