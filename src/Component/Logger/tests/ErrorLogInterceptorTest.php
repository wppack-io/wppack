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

namespace WpPack\Component\Logger\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\ErrorLogInterceptor;

final class ErrorLogInterceptorTest extends TestCase
{
    private ?string $savedErrorLog = null;
    private ?ErrorLogInterceptor $interceptor = null;

    protected function setUp(): void
    {
        $this->savedErrorLog = ini_get('error_log') ?: '';
    }

    protected function tearDown(): void
    {
        $this->interceptor?->restore();
        ini_set('error_log', $this->savedErrorLog ?? '');

        // Reset singleton
        $ref = new \ReflectionProperty(ErrorLogInterceptor::class, 'instance');
        $ref->setValue(null, null);
    }

    #[Test]
    public function registerCreatesTemporaryFile(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        self::assertTrue($interceptor->isRegistered());
        self::assertNotNull($interceptor->getTempFile());
        self::assertFileExists($interceptor->getTempFile());
        self::assertStringStartsWith((string) realpath(sys_get_temp_dir()), (string) realpath($interceptor->getTempFile()));
    }

    #[Test]
    public function registerRedirectsErrorLog(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        self::assertSame($interceptor->getTempFile(), ini_get('error_log'));
    }

    #[Test]
    public function restoreRevertsErrorLogAndDeletesTempFile(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();
        $tempFile = $interceptor->getTempFile();

        $interceptor->restore();

        self::assertFalse($interceptor->isRegistered());
        self::assertNull($interceptor->getTempFile());
        self::assertSame($this->savedErrorLog, ini_get('error_log'));
        self::assertFileDoesNotExist($tempFile);
    }

    #[Test]
    public function doubleRegisterIsIdempotent(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();
        $tempFile = $interceptor->getTempFile();
        $interceptor->register();

        self::assertSame($tempFile, $interceptor->getTempFile());
    }

    #[Test]
    public function doubleRestoreIsIdempotent(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();
        $interceptor->restore();
        $interceptor->restore();

        self::assertFalse($interceptor->isRegistered());
    }

    #[Test]
    public function collectCapturesErrorLogOutput(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level, 'message' => $message];
        });

        error_log('Test message from error_log');

        $interceptor->collect();

        self::assertCount(1, $captured);
        self::assertSame('debug', $captured[0]['level']);
        self::assertSame('Test message from error_log', $captured[0]['message']);
    }

    #[Test]
    public function collectCapturesMultipleMessages(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = $message;
        });

        error_log('First message');
        error_log('Second message');

        $interceptor->collect();

        self::assertCount(2, $captured);
        self::assertSame('First message', $captured[0]);
        self::assertSame('Second message', $captured[1]);
    }

    #[Test]
    public function collectDoesNotCapturePreRegistrationEntries(): void
    {
        // Temp file is fresh, so no pre-registration concern
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = $message;
        });

        // Only post-registration messages should appear
        error_log('Post-registration message');

        $interceptor->collect();

        self::assertCount(1, $captured);
        self::assertSame('Post-registration message', $captured[0]);
    }

    #[Test]
    public function collectDoesNothingWhenNoNewEntries(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = $message;
        });

        $interceptor->collect();

        self::assertCount(0, $captured);
    }

    #[Test]
    public function collectDoesNothingWhenNotRegistered(): void
    {
        $interceptor = $this->createInterceptor();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = $message;
        });

        $interceptor->collect();

        self::assertCount(0, $captured);
    }

    #[Test]
    public function parsesPhpWarningFormat(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level, 'message' => $message];
        });

        file_put_contents($interceptor->getTempFile(), "[29-Mar-2026 10:00:00 UTC] PHP Warning: some warning in /path/to/file.php on line 42\n", \FILE_APPEND);

        $interceptor->collect();

        self::assertCount(1, $captured);
        self::assertSame('warning', $captured[0]['level']);
        self::assertSame('some warning in /path/to/file.php on line 42', $captured[0]['message']);
    }

    #[Test]
    public function parsesPhpNoticeFormat(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level];
        });

        file_put_contents($interceptor->getTempFile(), "[29-Mar-2026 10:00:00 UTC] PHP Notice: Undefined variable\n", \FILE_APPEND);

        $interceptor->collect();

        self::assertSame('notice', $captured[0]['level']);
    }

    #[Test]
    public function parsesPhpFatalErrorFormat(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level];
        });

        file_put_contents($interceptor->getTempFile(), "[29-Mar-2026 10:00:00 UTC] PHP Fatal error: Out of memory\n", \FILE_APPEND);

        $interceptor->collect();

        self::assertSame('critical', $captured[0]['level']);
    }

    #[Test]
    public function parsesMultiLineStackTrace(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level, 'message' => $message];
        });

        $entry = "[29-Mar-2026 10:00:00 UTC] PHP Fatal error: Uncaught Exception: fail\n"
            . "Stack trace:\n"
            . "#0 /path/file.php(10): foo()\n"
            . "#1 {main}\n";
        file_put_contents($interceptor->getTempFile(), $entry, \FILE_APPEND);

        $interceptor->collect();

        self::assertCount(1, $captured);
        self::assertSame('critical', $captured[0]['level']);
        self::assertStringContainsString('Stack trace:', $captured[0]['message']);
    }

    #[Test]
    public function listenersReceiveCapturedEntries(): void
    {
        $interceptor = $this->createInterceptor();
        $interceptor->register();

        $captured = [];
        $interceptor->addListener(function (string $level, string $message) use (&$captured): void {
            $captured[] = ['level' => $level, 'message' => $message];
        });

        error_log('Listener test message');

        $interceptor->collect();

        self::assertNotEmpty($captured);
        self::assertSame('debug', $captured[0]['level']);
        self::assertSame('Listener test message', $captured[0]['message']);
    }

    private function createInterceptor(): ErrorLogInterceptor
    {
        $this->interceptor = new ErrorLogInterceptor();

        return $this->interceptor;
    }
}
