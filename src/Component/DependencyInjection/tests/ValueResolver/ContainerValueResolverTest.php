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

namespace WpPack\Component\DependencyInjection\Tests\ValueResolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WpPack\Component\DependencyInjection\ValueResolver\ContainerValueResolver;

final class ContainerValueResolverTest extends TestCase
{
    #[Test]
    public function supportsParameterWhenContainerHasService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(\DateTimeInterface::class)
            ->willReturn(true);

        $resolver = new ContainerValueResolver($container);

        $param = new \ReflectionParameter(
            static fn(\DateTimeInterface $date) => null,
            'date',
        );

        self::assertTrue($resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportParameterWhenContainerLacksService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $resolver = new ContainerValueResolver($container);

        $param = new \ReflectionParameter(
            static fn(\DateTimeInterface $date) => null,
            'date',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportBuiltinType(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');

        $resolver = new ContainerValueResolver($container);

        $param = new \ReflectionParameter(
            static fn(string $name) => null,
            'name',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportUntypedParameter(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('has');

        $resolver = new ContainerValueResolver($container);

        $param = new \ReflectionParameter(
            static fn($value) => null,
            'value',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function resolvesServiceFromContainer(): void
    {
        $date = new \DateTimeImmutable('2026-01-01');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(\DateTimeImmutable::class)
            ->willReturn(true);
        $container->method('get')
            ->with(\DateTimeImmutable::class)
            ->willReturn($date);

        $resolver = new ContainerValueResolver($container);

        $param = new \ReflectionParameter(
            static fn(\DateTimeImmutable $date) => null,
            'date',
        );

        self::assertSame($date, $resolver->resolve($param));
    }
}
