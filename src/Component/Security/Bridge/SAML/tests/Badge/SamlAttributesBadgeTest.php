<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests\Badge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\Badge\SamlAttributesBadge;

#[CoversClass(SamlAttributesBadge::class)]
final class SamlAttributesBadgeTest extends TestCase
{
    #[Test]
    public function getNameId(): void
    {
        $badge = new SamlAttributesBadge(
            'user@example.com',
            ['email' => ['user@example.com']],
            null,
        );

        self::assertSame('user@example.com', $badge->getNameId());
    }

    #[Test]
    public function getAttributes(): void
    {
        $attributes = [
            'email' => ['user@example.com'],
            'role' => ['admin', 'editor'],
        ];

        $badge = new SamlAttributesBadge('user@example.com', $attributes, null);

        self::assertSame($attributes, $badge->getAttributes());
    }

    #[Test]
    public function getAttributeReturnsFirstValue(): void
    {
        $badge = new SamlAttributesBadge(
            'user@example.com',
            ['role' => ['admin', 'editor']],
            null,
        );

        self::assertSame('admin', $badge->getAttribute('role'));
    }

    #[Test]
    public function getAttributeReturnsNullForMissingKey(): void
    {
        $badge = new SamlAttributesBadge('user@example.com', [], null);

        self::assertNull($badge->getAttribute('missing'));
    }

    #[Test]
    public function getAttributeValues(): void
    {
        $badge = new SamlAttributesBadge(
            'user@example.com',
            ['role' => ['admin', 'editor']],
            null,
        );

        self::assertSame(['admin', 'editor'], $badge->getAttributeValues('role'));
    }

    #[Test]
    public function getAttributeValuesReturnsEmptyArrayForMissingKey(): void
    {
        $badge = new SamlAttributesBadge('user@example.com', [], null);

        self::assertSame([], $badge->getAttributeValues('missing'));
    }

    #[Test]
    public function getSessionIndex(): void
    {
        $badge = new SamlAttributesBadge(
            'user@example.com',
            [],
            '_session123',
        );

        self::assertSame('_session123', $badge->getSessionIndex());
    }

    #[Test]
    public function getSessionIndexReturnsNullWhenNull(): void
    {
        $badge = new SamlAttributesBadge('user@example.com', [], null);

        self::assertNull($badge->getSessionIndex());
    }

    #[Test]
    public function isResolvedAlwaysReturnsTrue(): void
    {
        $badge = new SamlAttributesBadge('user@example.com', [], null);

        self::assertTrue($badge->isResolved());
    }
}
