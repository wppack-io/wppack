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

namespace WPPack\Component\Setting\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Setting\Attribute\AsSettingsPage;

#[CoversClass(AsSettingsPage::class)]
final class AsSettingsPageTest extends TestCase
{
    #[Test]
    public function storesAllProperties(): void
    {
        $attr = new AsSettingsPage(
            slug: 'wppack-options',
            label: 'WPPack Settings',
            menuLabel: 'WPPack',
            optionName: 'wppack_options',
            optionGroup: 'wppack_group',
            parent: 'themes.php',
            icon: 'dashicons-admin-plugins',
            position: 42,
            scope: AdminScope::Network,
        );

        self::assertSame('wppack-options', $attr->slug);
        self::assertSame('WPPack Settings', $attr->label);
        self::assertSame('WPPack', $attr->menuLabel);
        self::assertSame('wppack_options', $attr->optionName);
        self::assertSame('wppack_group', $attr->optionGroup);
        self::assertSame('themes.php', $attr->parent);
        self::assertSame('dashicons-admin-plugins', $attr->icon);
        self::assertSame(42, $attr->position);
        self::assertSame(AdminScope::Network, $attr->scope);
    }

    #[Test]
    public function defaults(): void
    {
        $attr = new AsSettingsPage(slug: 'x', label: 'X');

        self::assertSame('', $attr->menuLabel);
        self::assertSame('', $attr->optionName);
        self::assertSame('', $attr->optionGroup);
        self::assertSame('options-general.php', $attr->parent, 'default parent is options-general');
        self::assertNull($attr->icon);
        self::assertNull($attr->position);
        self::assertSame(AdminScope::Site, $attr->scope);
    }

    #[Test]
    public function targetsClassesOnly(): void
    {
        $ref = new \ReflectionClass(AsSettingsPage::class);
        $flags = $ref->getAttributes(\Attribute::class)[0]->getArguments()[0];

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
