<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\ObjectCacheConfig;

final class ObjectCacheConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new ObjectCacheConfig();

        self::assertSame('wp:', $config->prefix);
        self::assertSame([], $config->hashStrategies);
        self::assertNull($config->maxTtl);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new ObjectCacheConfig(
            prefix: 'test:',
            maxTtl: 3600,
        );

        self::assertSame('test:', $config->prefix);
        self::assertSame(3600, $config->maxTtl);
    }
}
