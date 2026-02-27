<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Attribute\AsAlias;

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
