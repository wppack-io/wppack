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

namespace WPPack\Component\Debug\Tests\ErrorHandler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WPPack\Component\Debug\ErrorHandler\FatalErrorHandler;

final class FatalErrorHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function handleDoesNothingWhenAccessDenied(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $handler = new FatalErrorHandler(
            new ErrorRenderer(),
            new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']),
        );

        // error_get_last() returns whatever the last error was;
        // handle() should bail out before rendering because access is denied.
        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function handleDoesNothingWhenNoFatalError(): void
    {
        $handler = new FatalErrorHandler(new ErrorRenderer());

        // With no fatal error in error_get_last(), handle() should produce no output
        @trigger_error('', E_USER_NOTICE);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    #[Test]
    public function constructorAcceptsNullConfig(): void
    {
        // Verify the handler can be constructed without a config (backwards compatibility)
        $handler = new FatalErrorHandler(new ErrorRenderer());

        self::assertInstanceOf(FatalErrorHandler::class, $handler);
    }
}
