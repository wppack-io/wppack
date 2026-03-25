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

namespace WpPack\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class CompilerPassAdapterTest extends TestCase
{
    #[Test]
    public function wpPackPassIsExecutedDuringCompile(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('test.service', \stdClass::class);

        $executed = false;
        $pass = new class ($executed) implements CompilerPassInterface {
            public function __construct(private bool &$executed) {}

            public function process(ContainerBuilder $builder): void
            {
                $this->executed = true;
                $builder->findDefinition('test.service')->setArgument(0, 'from-pass');
            }
        };

        $builder->addCompilerPass($pass);
        $container = $builder->compile();

        self::assertTrue($executed);
    }

    #[Test]
    public function passReceivesWpPackContainerBuilder(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('test.service', \stdClass::class);

        $receivedBuilder = null;
        $pass = new class ($receivedBuilder) implements CompilerPassInterface {
            public function __construct(private ?ContainerBuilder &$receivedBuilder) {}

            public function process(ContainerBuilder $builder): void
            {
                $this->receivedBuilder = $builder;
            }
        };

        $builder->addCompilerPass($pass);
        $builder->compile();

        self::assertSame($builder, $receivedBuilder);
    }

    #[Test]
    public function multiplePassesExecuteInOrder(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('test.service', \stdClass::class);

        $order = [];
        $pass1 = new class ($order) implements CompilerPassInterface {
            public function __construct(private array &$order) {}

            public function process(ContainerBuilder $builder): void
            {
                $this->order[] = 'first';
            }
        };
        $pass2 = new class ($order) implements CompilerPassInterface {
            public function __construct(private array &$order) {}

            public function process(ContainerBuilder $builder): void
            {
                $this->order[] = 'second';
            }
        };

        $builder->addCompilerPass($pass1);
        $builder->addCompilerPass($pass2);
        $builder->compile();

        self::assertSame(['first', 'second'], $order);
    }
}
