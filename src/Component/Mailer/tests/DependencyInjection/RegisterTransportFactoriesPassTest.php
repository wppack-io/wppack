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

namespace WpPack\Component\Mailer\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\Transport;

final class RegisterTransportFactoriesPassTest extends TestCase
{
    #[Test]
    public function processRegistersFactoriesOnTransportDefinition(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Transport::class, Transport::class);
        $builder->register(NativeTransportFactory::class, NativeTransportFactory::class)
            ->addTag('mailer.transport_factory');

        $pass = new RegisterTransportFactoriesPass();
        $pass->process($builder);

        $arguments = $builder->findDefinition(Transport::class)->getArguments();
        self::assertCount(1, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[0][0]);
    }

    #[Test]
    public function processReturnsEarlyWhenTransportDefinitionMissing(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(NativeTransportFactory::class, NativeTransportFactory::class)
            ->addTag('mailer.transport_factory');

        $pass = new RegisterTransportFactoriesPass();
        $pass->process($builder);

        // No exception thrown, no Transport definition modified
        self::assertFalse($builder->hasDefinition(Transport::class));
    }
}
