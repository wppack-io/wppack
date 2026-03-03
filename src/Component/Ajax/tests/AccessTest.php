<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;

final class AccessTest extends TestCase
{
    #[Test]
    public function publicCaseExists(): void
    {
        self::assertSame('Public', Access::Public->name);
    }

    #[Test]
    public function authenticatedCaseExists(): void
    {
        self::assertSame('Authenticated', Access::Authenticated->name);
    }

    #[Test]
    public function guestCaseExists(): void
    {
        self::assertSame('Guest', Access::Guest->name);
    }

    #[Test]
    public function hasExactlyThreeCases(): void
    {
        self::assertCount(3, Access::cases());
    }
}
