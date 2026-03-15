<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Input;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Exception\InvalidArgumentException;
use WpPack\Component\Console\Exception\LogicException;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputOption;

final class InputDefinitionTest extends TestCase
{
    #[Test]
    public function addAndGetArgument(): void
    {
        $definition = new InputDefinition();
        $argument = new InputArgument('file', InputArgument::REQUIRED, 'CSV file');

        $result = $definition->addArgument($argument);

        self::assertSame($definition, $result);
        self::assertTrue($definition->hasArgument('file'));
        self::assertSame($argument, $definition->getArgument('file'));
        self::assertCount(1, $definition->getArguments());
    }

    #[Test]
    public function addAndGetOption(): void
    {
        $definition = new InputDefinition();
        $option = new InputOption('verbose', InputOption::VALUE_NONE, 'Verbose');

        $result = $definition->addOption($option);

        self::assertSame($definition, $result);
        self::assertTrue($definition->hasOption('verbose'));
        self::assertSame($option, $definition->getOption('verbose'));
        self::assertCount(1, $definition->getOptions());
    }

    #[Test]
    public function duplicateArgumentThrows(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('file'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An argument with name "file" already exists.');

        $definition->addArgument(new InputArgument('file'));
    }

    #[Test]
    public function duplicateOptionThrows(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('verbose', InputOption::VALUE_NONE));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An option with name "verbose" already exists.');

        $definition->addOption(new InputOption('verbose', InputOption::VALUE_NONE));
    }

    #[Test]
    public function cannotAddArgumentAfterArray(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add an argument after an array argument.');

        $definition->addArgument(new InputArgument('extra', InputArgument::OPTIONAL));
    }

    #[Test]
    public function cannotAddRequiredAfterOptional(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('optional', InputArgument::OPTIONAL));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add a required argument after an optional one.');

        $definition->addArgument(new InputArgument('required', InputArgument::REQUIRED));
    }

    #[Test]
    public function getNonExistentArgumentThrows(): void
    {
        $definition = new InputDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "missing" argument does not exist.');

        $definition->getArgument('missing');
    }

    #[Test]
    public function getNonExistentOptionThrows(): void
    {
        $definition = new InputDefinition();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--missing" option does not exist.');

        $definition->getOption('missing');
    }

    #[Test]
    public function hasArgumentReturnsFalse(): void
    {
        $definition = new InputDefinition();

        self::assertFalse($definition->hasArgument('missing'));
    }

    #[Test]
    public function hasOptionReturnsFalse(): void
    {
        $definition = new InputDefinition();

        self::assertFalse($definition->hasOption('missing'));
    }

    #[Test]
    public function toSynopsisWithPositionalArgument(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertSame('positional', $synopsis[0]['type']);
        self::assertSame('file', $synopsis[0]['name']);
        self::assertSame('CSV file', $synopsis[0]['description']);
        self::assertFalse($synopsis[0]['optional']);
    }

    #[Test]
    public function toSynopsisWithOptionalArrayArgument(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Files'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertTrue($synopsis[0]['optional']);
        self::assertTrue($synopsis[0]['repeating']);
    }

    #[Test]
    public function toSynopsisWithFlagOption(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip emails'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertSame('flag', $synopsis[0]['type']);
        self::assertSame('skip-email', $synopsis[0]['name']);
        self::assertTrue($synopsis[0]['optional']);
    }

    #[Test]
    public function toSynopsisWithAssocOption(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'User role', 'subscriber'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertSame('assoc', $synopsis[0]['type']);
        self::assertSame('role', $synopsis[0]['name']);
        self::assertTrue($synopsis[0]['optional']);
        self::assertSame('subscriber', $synopsis[0]['default']);
    }

    #[Test]
    public function toSynopsisWithRequiredOption(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('format', InputOption::VALUE_REQUIRED, 'Format'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(1, $synopsis);
        self::assertSame('assoc', $synopsis[0]['type']);
        self::assertFalse($synopsis[0]['optional']);
    }

    #[Test]
    public function toSynopsisCombined(): void
    {
        $definition = new InputDefinition();
        $definition
            ->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file'))
            ->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'Role', 'subscriber'))
            ->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip emails'));

        $synopsis = $definition->toSynopsis();

        self::assertCount(3, $synopsis);
        self::assertSame('positional', $synopsis[0]['type']);
        self::assertSame('assoc', $synopsis[1]['type']);
        self::assertSame('flag', $synopsis[2]['type']);
    }
}
