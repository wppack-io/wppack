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

namespace WPPack\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as SymfonyCompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassAdapter;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(CompilerPassAdapter::class)]
final class CompilerPassAdapterDirectTest extends TestCase
{
    #[Test]
    public function implementsSymfonyCompilerPassInterface(): void
    {
        $adapter = new CompilerPassAdapter(
            $this->createMock(CompilerPassInterface::class),
            new ContainerBuilder(),
        );

        self::assertInstanceOf(SymfonyCompilerPassInterface::class, $adapter);
    }

    #[Test]
    public function forwardsProcessToWpPackBuilderIgnoringSymfonyArgument(): void
    {
        $wpPackBuilder = new ContainerBuilder();
        $receivedBuilder = null;

        $innerPass = $this->createMock(CompilerPassInterface::class);
        $innerPass->expects(self::once())
            ->method('process')
            ->willReturnCallback(function (ContainerBuilder $b) use (&$receivedBuilder): void {
                $receivedBuilder = $b;
            });

        $adapter = new CompilerPassAdapter($innerPass, $wpPackBuilder);

        $adapter->process(new SymfonyContainerBuilder());

        self::assertSame($wpPackBuilder, $receivedBuilder);
    }
}
