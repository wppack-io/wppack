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

namespace WPPack\Component\Console\Tests\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Input\InputArgument;
use WPPack\Component\Console\Input\InputDefinition;
use WPPack\Component\Console\Input\InputOption;

#[CoversClass(InputDefinition::class)]
final class InputDefinitionSynopsisTest extends TestCase
{
    #[Test]
    public function toSynopsisWithArgumentDefault(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('format', InputArgument::OPTIONAL, 'Output format', 'json'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertSame('positional', $synopsis[0]['type']);
        self::assertTrue($synopsis[0]['optional']);
        self::assertSame('json', $synopsis[0]['default']);
    }

    #[Test]
    public function toSynopsisArgumentWithoutDefault(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('name', InputArgument::REQUIRED, 'Name'));

        $synopsis = $definition->toSynopsis();

        self::assertArrayNotHasKey('default', $synopsis[0]);
    }

    #[Test]
    public function toSynopsisOptionWithoutDefault(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('format', InputOption::VALUE_OPTIONAL, 'Format'));

        $synopsis = $definition->toSynopsis();

        self::assertArrayNotHasKey('default', $synopsis[0]);
    }

    #[Test]
    public function toSynopsisEmptyDefinition(): void
    {
        $definition = new InputDefinition();

        $synopsis = $definition->toSynopsis();

        self::assertSame([], $synopsis);
    }

    #[Test]
    public function toSynopsisRequiredArrayArgument(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('files', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Files'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertFalse($synopsis[0]['optional']);
        self::assertTrue($synopsis[0]['repeating']);
    }

    #[Test]
    public function toSynopsisFlagOptionDescription(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('verbose', InputOption::VALUE_NONE, 'Enable verbose'));

        $synopsis = $definition->toSynopsis();

        self::assertSame('Enable verbose', $synopsis[0]['description']);
    }

    #[Test]
    public function toSynopsisWithRequiredOptionAndDefault(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('format', InputOption::VALUE_REQUIRED, 'Format', 'json'));

        $synopsis = $definition->toSynopsis();

        self::assertFalse($synopsis[0]['optional']);
        self::assertSame('json', $synopsis[0]['default']);
    }
}
