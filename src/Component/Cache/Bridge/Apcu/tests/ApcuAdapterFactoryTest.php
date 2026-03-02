<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\Apcu\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Bridge\Apcu\ApcuAdapter;
use WpPack\Component\Cache\Bridge\Apcu\ApcuAdapterFactory;

final class ApcuAdapterFactoryTest extends TestCase
{
    private ApcuAdapterFactory $factory;

    protected function setUp(): void
    {
        if (!\function_exists('apcu_enabled')) {
            self::markTestSkipped('ext-apcu is not available.');
        }

        $this->factory = new ApcuAdapterFactory();
    }

    #[Test]
    public function supportsApcuScheme(): void
    {
        self::assertTrue($this->factory->supports(Dsn::fromString('apcu://')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        self::assertFalse($this->factory->supports(Dsn::fromString('redis://localhost')));
    }

    #[Test]
    public function createsApcuAdapter(): void
    {
        $adapter = $this->factory->create(Dsn::fromString('apcu://'));

        self::assertInstanceOf(ApcuAdapter::class, $adapter);
        self::assertSame('apcu', $adapter->getName());
    }
}
