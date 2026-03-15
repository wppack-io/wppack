<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Tests\Input;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Command\Exception\InvalidArgumentException;
use WpPack\Component\Command\Input\InputArgument;
use WpPack\Component\Command\Input\InputDefinition;
use WpPack\Component\Command\Input\InputOption;
use WpPack\Component\Command\Input\WpCliInput;

final class WpCliInputTest extends TestCase
{
    #[Test]
    public function resolvesPositionalArguments(): void
    {
        $definition = new InputDefinition();
        $definition
            ->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file'))
            ->addArgument(new InputArgument('format', InputArgument::OPTIONAL, 'Format', 'json'));

        $input = new WpCliInput($definition, ['/path/to/file.csv'], []);

        self::assertSame('/path/to/file.csv', $input->getArgument('file'));
        self::assertSame('json', $input->getArgument('format'));
    }

    #[Test]
    public function resolvesAllPositionalArguments(): void
    {
        $definition = new InputDefinition();
        $definition
            ->addArgument(new InputArgument('file', InputArgument::REQUIRED))
            ->addArgument(new InputArgument('format', InputArgument::OPTIONAL));

        $input = new WpCliInput($definition, ['/path.csv', 'csv'], []);

        self::assertSame('/path.csv', $input->getArgument('file'));
        self::assertSame('csv', $input->getArgument('format'));
    }

    #[Test]
    public function resolvesArrayArgument(): void
    {
        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY));

        $input = new WpCliInput($definition, ['a.csv', 'b.csv', 'c.csv'], []);

        self::assertSame(['a.csv', 'b.csv', 'c.csv'], $input->getArgument('files'));
    }

    #[Test]
    public function resolvesFlagOption(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip'));

        $input = new WpCliInput($definition, [], ['skip-email' => '']);

        self::assertTrue($input->getOption('skip-email'));
    }

    #[Test]
    public function resolvesFlagOptionWhenAbsent(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip'));

        $input = new WpCliInput($definition, [], []);

        self::assertFalse($input->getOption('skip-email'));
    }

    #[Test]
    public function resolvesAssocOption(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'Role', 'subscriber'));

        $input = new WpCliInput($definition, [], ['role' => 'editor']);

        self::assertSame('editor', $input->getOption('role'));
    }

    #[Test]
    public function resolvesAssocOptionDefault(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'Role', 'subscriber'));

        $input = new WpCliInput($definition, [], []);

        self::assertSame('subscriber', $input->getOption('role'));
    }

    #[Test]
    public function hasOptionReturnsTrue(): void
    {
        $definition = new InputDefinition();
        $definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL));

        $input = new WpCliInput($definition, [], []);

        self::assertTrue($input->hasOption('role'));
    }

    #[Test]
    public function hasOptionReturnsFalse(): void
    {
        $definition = new InputDefinition();

        $input = new WpCliInput($definition, [], []);

        self::assertFalse($input->hasOption('missing'));
    }

    #[Test]
    public function getNonExistentArgumentThrows(): void
    {
        $definition = new InputDefinition();
        $input = new WpCliInput($definition, [], []);

        $this->expectException(InvalidArgumentException::class);

        $input->getArgument('missing');
    }

    #[Test]
    public function getNonExistentOptionThrows(): void
    {
        $definition = new InputDefinition();
        $input = new WpCliInput($definition, [], []);

        $this->expectException(InvalidArgumentException::class);

        $input->getOption('missing');
    }
}
