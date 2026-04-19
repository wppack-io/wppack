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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Exception\InvalidArgumentException;
use WPPack\Component\Console\Input\InputOption;

final class InputOptionTest extends TestCase
{
    #[Test]
    public function flagOption(): void
    {
        $option = new InputOption('verbose', InputOption::VALUE_NONE, 'Verbose output');

        self::assertSame('verbose', $option->name);
        self::assertSame('Verbose output', $option->description);
        self::assertTrue($option->isValueNone());
        self::assertFalse($option->isValueRequired());
        self::assertFalse($option->isValueOptional());
        self::assertNull($option->default);
    }

    #[Test]
    public function requiredValueOption(): void
    {
        $option = new InputOption('format', InputOption::VALUE_REQUIRED, 'Output format');

        self::assertFalse($option->isValueNone());
        self::assertTrue($option->isValueRequired());
        self::assertFalse($option->isValueOptional());
    }

    #[Test]
    public function optionalValueOption(): void
    {
        $option = new InputOption('role', InputOption::VALUE_OPTIONAL, 'User role', 'subscriber');

        self::assertFalse($option->isValueNone());
        self::assertFalse($option->isValueRequired());
        self::assertTrue($option->isValueOptional());
        self::assertSame('subscriber', $option->default);
    }

    #[Test]
    public function emptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An option name cannot be empty.');

        new InputOption('');
    }

    #[Test]
    public function flagWithDefaultThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A flag option (VALUE_NONE) cannot have a default value.');

        new InputOption('verbose', InputOption::VALUE_NONE, '', 'yes');
    }
}
