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

namespace WPPack\Component\Admin\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;

#[CoversClass(AdminScope::class)]
#[CoversClass(AsAdminPage::class)]
final class AttributesTest extends TestCase
{
    #[Test]
    public function adminScopeCases(): void
    {
        self::assertSame('site', AdminScope::Site->value);
        self::assertSame('network', AdminScope::Network->value);
        self::assertSame('auto', AdminScope::Auto->value);
        self::assertCount(3, AdminScope::cases());
        self::assertSame(AdminScope::Site, AdminScope::from('site'));
    }

    #[Test]
    public function adminScopeRejectsUnknownValue(): void
    {
        $this->expectException(\ValueError::class);

        AdminScope::from('global');
    }

    #[Test]
    public function asAdminPageStoresAllProperties(): void
    {
        $attr = new AsAdminPage(
            slug: 'my-page',
            label: 'My Plugin',
            menuLabel: 'Plugin Menu',
            parent: 'options-general.php',
            icon: 'dashicons-admin-generic',
            position: 100,
            scope: AdminScope::Network,
            textDomain: 'wppack',
        );

        self::assertSame('my-page', $attr->slug);
        self::assertSame('My Plugin', $attr->label);
        self::assertSame('Plugin Menu', $attr->menuLabel);
        self::assertSame('options-general.php', $attr->parent);
        self::assertSame('dashicons-admin-generic', $attr->icon);
        self::assertSame(100, $attr->position);
        self::assertSame(AdminScope::Network, $attr->scope);
        self::assertSame('wppack', $attr->textDomain);
    }

    #[Test]
    public function asAdminPageDefaults(): void
    {
        $attr = new AsAdminPage(slug: 'x', label: 'X');

        self::assertSame('', $attr->menuLabel);
        self::assertNull($attr->parent);
        self::assertNull($attr->icon);
        self::assertNull($attr->position);
        self::assertSame(AdminScope::Site, $attr->scope);
        self::assertNull($attr->textDomain);
    }

    #[Test]
    public function asAdminPageTargetsClasses(): void
    {
        $ref = new \ReflectionClass(AsAdminPage::class);
        $flags = $ref->getAttributes(\Attribute::class)[0]->getArguments()[0];

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
