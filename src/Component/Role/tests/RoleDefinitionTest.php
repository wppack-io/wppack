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

namespace WPPack\Component\Role\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\RoleDefinition;

#[CoversClass(RoleDefinition::class)]
final class RoleDefinitionTest extends TestCase
{
    #[Test]
    public function carriesEveryField(): void
    {
        $def = new RoleDefinition(
            name: 'editor',
            label: 'Editor',
            capabilities: ['edit_posts', 'delete_posts'],
        );

        self::assertSame('editor', $def->name);
        self::assertSame('Editor', $def->label);
        self::assertSame(['edit_posts', 'delete_posts'], $def->capabilities);
    }

    #[Test]
    public function emptyCapabilitiesAreAllowed(): void
    {
        $def = new RoleDefinition(name: 'visitor', label: 'Visitor', capabilities: []);

        self::assertSame([], $def->capabilities);
    }
}
