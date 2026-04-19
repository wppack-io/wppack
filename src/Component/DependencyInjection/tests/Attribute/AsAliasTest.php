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

namespace WPPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\Attribute\AsAlias;

final class AsAliasTest extends TestCase
{
    #[Test]
    public function storesId(): void
    {
        $attr = new AsAlias(id: 'my.alias');

        self::assertSame('my.alias', $attr->id);
    }

    #[Test]
    public function isRepeatable(): void
    {
        $reflection = new \ReflectionClass(Fixtures\MultiAliasService::class);
        $attributes = $reflection->getAttributes(AsAlias::class);

        self::assertCount(2, $attributes);

        /** @var AsAlias $first */
        $first = $attributes[0]->newInstance();
        self::assertSame('alias.one', $first->id);

        /** @var AsAlias $second */
        $second = $attributes[1]->newInstance();
        self::assertSame('alias.two', $second->id);
    }
}
