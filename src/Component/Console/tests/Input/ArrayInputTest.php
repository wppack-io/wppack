<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Input;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Exception\InvalidArgumentException;
use WpPack\Component\Console\Input\ArrayInput;

final class ArrayInputTest extends TestCase
{
    #[Test]
    public function getArgument(): void
    {
        $input = new ArrayInput(['file' => '/path/to/file.csv']);

        self::assertSame('/path/to/file.csv', $input->getArgument('file'));
    }

    #[Test]
    public function getArgumentWithNull(): void
    {
        $input = new ArrayInput(['file' => null]);

        self::assertNull($input->getArgument('file'));
    }

    #[Test]
    public function getNonExistentArgumentThrows(): void
    {
        $input = new ArrayInput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "missing" argument does not exist.');

        $input->getArgument('missing');
    }

    #[Test]
    public function getOption(): void
    {
        $input = new ArrayInput([], ['role' => 'editor']);

        self::assertSame('editor', $input->getOption('role'));
    }

    #[Test]
    public function getOptionWithBool(): void
    {
        $input = new ArrayInput([], ['skip-email' => true]);

        self::assertTrue($input->getOption('skip-email'));
    }

    #[Test]
    public function getNonExistentOptionThrows(): void
    {
        $input = new ArrayInput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--missing" option does not exist.');

        $input->getOption('missing');
    }

    #[Test]
    public function hasOptionReturnsTrue(): void
    {
        $input = new ArrayInput([], ['role' => 'editor']);

        self::assertTrue($input->hasOption('role'));
    }

    #[Test]
    public function hasOptionReturnsFalse(): void
    {
        $input = new ArrayInput();

        self::assertFalse($input->hasOption('missing'));
    }
}
