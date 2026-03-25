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

namespace WpPack\Component\Console\Tests\Input;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Exception\InvalidArgumentException;
use WpPack\Component\Console\Input\InputArgument;

final class InputArgumentTest extends TestCase
{
    #[Test]
    public function requiredArgument(): void
    {
        $argument = new InputArgument('file', InputArgument::REQUIRED, 'CSV file path');

        self::assertSame('file', $argument->name);
        self::assertSame('CSV file path', $argument->description);
        self::assertTrue($argument->isRequired());
        self::assertFalse($argument->isArray());
        self::assertNull($argument->default);
    }

    #[Test]
    public function optionalArgument(): void
    {
        $argument = new InputArgument('format', InputArgument::OPTIONAL, 'Output format', 'json');

        self::assertFalse($argument->isRequired());
        self::assertFalse($argument->isArray());
        self::assertSame('json', $argument->default);
    }

    #[Test]
    public function arrayArgument(): void
    {
        $argument = new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'File list');

        self::assertFalse($argument->isRequired());
        self::assertTrue($argument->isArray());
    }

    #[Test]
    public function emptyNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An argument name cannot be empty.');

        new InputArgument('');
    }

    #[Test]
    public function requiredWithDefaultThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A required argument cannot have a default value.');

        new InputArgument('file', InputArgument::REQUIRED, '', 'default');
    }

    #[Test]
    public function requiredArrayArgument(): void
    {
        $argument = new InputArgument('files', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'File list');

        self::assertTrue($argument->isRequired());
        self::assertTrue($argument->isArray());
        self::assertNull($argument->default);
    }
}
