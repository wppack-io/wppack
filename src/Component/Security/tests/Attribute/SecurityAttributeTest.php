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

namespace WpPack\Component\Security\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Attribute\AsAuthenticator;
use WpPack\Component\Security\Attribute\AsVoter;
use WpPack\Component\Security\Attribute\CurrentUser;

#[CoversClass(AsAuthenticator::class)]
#[CoversClass(AsVoter::class)]
#[CoversClass(CurrentUser::class)]
final class SecurityAttributeTest extends TestCase
{
    // ---------------------------------------------------------------
    // AsAuthenticator
    // ---------------------------------------------------------------

    #[Test]
    public function asAuthenticatorDefaultPriority(): void
    {
        $attr = new AsAuthenticator();

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asAuthenticatorCustomPriority(): void
    {
        $attr = new AsAuthenticator(priority: 10);

        self::assertSame(10, $attr->priority);
    }

    #[Test]
    public function asAuthenticatorTargetsClass(): void
    {
        $reflection = new \ReflectionClass(AsAuthenticator::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    // ---------------------------------------------------------------
    // AsVoter
    // ---------------------------------------------------------------

    #[Test]
    public function asVoterDefaultPriority(): void
    {
        $attr = new AsVoter();

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asVoterCustomPriority(): void
    {
        $attr = new AsVoter(priority: 5);

        self::assertSame(5, $attr->priority);
    }

    #[Test]
    public function asVoterTargetsClass(): void
    {
        $reflection = new \ReflectionClass(AsVoter::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    // ---------------------------------------------------------------
    // CurrentUser
    // ---------------------------------------------------------------

    #[Test]
    public function currentUserTargetsParameter(): void
    {
        $reflection = new \ReflectionClass(CurrentUser::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PARAMETER, $attribute->flags);
    }

    #[Test]
    public function currentUserCanBeInstantiated(): void
    {
        $attr = new CurrentUser();

        self::assertInstanceOf(CurrentUser::class, $attr);
    }
}
