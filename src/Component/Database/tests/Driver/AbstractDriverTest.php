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

namespace WPPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Driver\AbstractDriver;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Platform\PlatformInterface;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;
use WPPack\Component\Database\Translator\NullQueryTranslator;

#[CoversClass(AbstractDriver::class)]
final class AbstractDriverTest extends TestCase
{
    #[Test]
    public function escapeStringContentDoublesSingleQuotes(): void
    {
        $driver = $this->concreteDriver();

        self::assertSame("O''Brien", $driver->escapeStringContent("O'Brien"));
        self::assertSame("it''s a ''quoted'' phrase", $driver->escapeStringContent("it's a 'quoted' phrase"));
        self::assertSame('no-quotes', $driver->escapeStringContent('no-quotes'));
    }

    #[Test]
    public function getQueryTranslatorDefaultsToNullQueryTranslator(): void
    {
        $driver = $this->concreteDriver();

        self::assertInstanceOf(NullQueryTranslator::class, $driver->getQueryTranslator());
    }

    #[Test]
    public function executeWrapsUnexpectedThrowableInDriverException(): void
    {
        $driver = new class extends AbstractDriver {
            public function getName(): string
            {
                return 'test';
            }

            protected function doConnect(): void
            {
                throw new \RuntimeException('boom');
            }

            protected function doClose(): void {}

            protected function doExecuteQuery(string $sql, array $params = []): Result
            {
                return new Result([]);
            }

            protected function doExecuteStatement(string $sql, array $params = []): int
            {
                return 0;
            }

            protected function doPrepare(string $sql): Statement
            {
                return new Statement(
                    static fn(array $p): Result => new Result([]),
                    static fn(array $p): int => 0,
                    static function (): void {},
                );
            }

            protected function doLastInsertId(): int
            {
                return 0;
            }

            protected function doBeginTransaction(): void {}

            protected function doCommit(): void {}

            protected function doRollBack(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function isConnected(): bool
            {
                return false;
            }

            public function getPlatform(): PlatformInterface
            {
                throw new \LogicException('not needed');
            }

            public function getNativeConnection(): mixed
            {
                return null;
            }

            public function quoteStringLiteral(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        };

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('boom');

        $driver->connect();
    }

    #[Test]
    public function executeRethrowsExistingDriverExceptionAsIs(): void
    {
        $original = new DriverException('original');

        $driver = new class ($original) extends AbstractDriver {
            public function __construct(private readonly DriverException $e) {}

            public function getName(): string
            {
                return 'test';
            }

            protected function doConnect(): void
            {
                throw $this->e;
            }

            protected function doClose(): void {}

            protected function doExecuteQuery(string $sql, array $params = []): Result
            {
                return new Result([]);
            }

            protected function doExecuteStatement(string $sql, array $params = []): int
            {
                return 0;
            }

            protected function doPrepare(string $sql): Statement
            {
                return new Statement(
                    static fn(array $p): Result => new Result([]),
                    static fn(array $p): int => 0,
                    static function (): void {},
                );
            }

            protected function doLastInsertId(): int
            {
                return 0;
            }

            protected function doBeginTransaction(): void {}

            protected function doCommit(): void {}

            protected function doRollBack(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function isConnected(): bool
            {
                return false;
            }

            public function getPlatform(): PlatformInterface
            {
                throw new \LogicException('not needed');
            }

            public function getNativeConnection(): mixed
            {
                return null;
            }

            public function quoteStringLiteral(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        };

        try {
            $driver->connect();
            self::fail('expected DriverException');
        } catch (DriverException $caught) {
            self::assertSame($original, $caught, 'existing DriverException should pass through unwrapped');
        }
    }

    private function concreteDriver(): AbstractDriver
    {
        return new class extends AbstractDriver {
            public function getName(): string
            {
                return 'test';
            }

            protected function doConnect(): void {}

            protected function doClose(): void {}

            protected function doExecuteQuery(string $sql, array $params = []): Result
            {
                return new Result([]);
            }

            protected function doExecuteStatement(string $sql, array $params = []): int
            {
                return 0;
            }

            protected function doPrepare(string $sql): Statement
            {
                return new Statement(
                    static fn(array $p): Result => new Result([]),
                    static fn(array $p): int => 0,
                    static function (): void {},
                );
            }

            protected function doLastInsertId(): int
            {
                return 0;
            }

            protected function doBeginTransaction(): void {}

            protected function doCommit(): void {}

            protected function doRollBack(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function isConnected(): bool
            {
                return true;
            }

            public function getPlatform(): PlatformInterface
            {
                throw new \LogicException('not needed');
            }

            public function getNativeConnection(): mixed
            {
                return null;
            }

            public function quoteStringLiteral(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }
        };
    }
}
