<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\Exception\InvalidArgumentException;
use WpPack\Component\Logger\Handler\ErrorLogHandler;

final class ErrorLogHandlerTest extends TestCase
{
    #[Test]
    public function defaultLevelHandlesAllLevels(): void
    {
        $handler = new ErrorLogHandler();

        self::assertTrue($handler->isHandling('emergency'));
        self::assertTrue($handler->isHandling('alert'));
        self::assertTrue($handler->isHandling('critical'));
        self::assertTrue($handler->isHandling('error'));
        self::assertTrue($handler->isHandling('warning'));
        self::assertTrue($handler->isHandling('notice'));
        self::assertTrue($handler->isHandling('info'));
        self::assertTrue($handler->isHandling('debug'));
    }

    #[Test]
    public function minimumLevelFiltering(): void
    {
        $handler = new ErrorLogHandler(level: 'warning');

        self::assertTrue($handler->isHandling('emergency'));
        self::assertTrue($handler->isHandling('alert'));
        self::assertTrue($handler->isHandling('critical'));
        self::assertTrue($handler->isHandling('error'));
        self::assertTrue($handler->isHandling('warning'));
        self::assertFalse($handler->isHandling('notice'));
        self::assertFalse($handler->isHandling('info'));
        self::assertFalse($handler->isHandling('debug'));
    }

    #[Test]
    public function errorOnlyLevel(): void
    {
        $handler = new ErrorLogHandler(level: 'error');

        self::assertTrue($handler->isHandling('emergency'));
        self::assertTrue($handler->isHandling('error'));
        self::assertFalse($handler->isHandling('warning'));
        self::assertFalse($handler->isHandling('debug'));
    }

    #[Test]
    public function invalidLevelThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ErrorLogHandler(level: 'invalid');
    }

    #[Test]
    public function handleFormatsMessageCorrectly(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $originalErrorLog = ini_set('error_log', $tempFile);

        try {
            $handler = new ErrorLogHandler();
            $handler->handle('error', 'Something failed', [
                '_channel' => 'app',
                'code' => 500,
            ]);

            $output = file_get_contents($tempFile);
            self::assertStringContainsString('[app.ERROR] Something failed', $output);
            self::assertStringContainsString('"code":500', $output);
            self::assertStringNotContainsString('_channel', $output);
        } finally {
            if ($originalErrorLog !== false) {
                ini_set('error_log', $originalErrorLog);
            } else {
                ini_restore('error_log');
            }
            @unlink($tempFile);
        }
    }

    #[Test]
    public function handleWithEmptyContextOmitsJson(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $originalErrorLog = ini_set('error_log', $tempFile);

        try {
            $handler = new ErrorLogHandler();
            $handler->handle('info', 'Simple message', ['_channel' => 'app']);

            $output = file_get_contents($tempFile);
            self::assertStringContainsString('[app.INFO] Simple message', $output);
            self::assertStringNotContainsString('{', $output);
        } finally {
            if ($originalErrorLog !== false) {
                ini_set('error_log', $originalErrorLog);
            } else {
                ini_restore('error_log');
            }
            @unlink($tempFile);
        }
    }

    #[Test]
    public function handleUsesDefaultChannelWhenMissing(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $originalErrorLog = ini_set('error_log', $tempFile);

        try {
            $handler = new ErrorLogHandler();
            $handler->handle('warning', 'No channel', []);

            $output = file_get_contents($tempFile);
            self::assertStringContainsString('[app.WARNING] No channel', $output);
        } finally {
            if ($originalErrorLog !== false) {
                ini_set('error_log', $originalErrorLog);
            } else {
                ini_restore('error_log');
            }
            @unlink($tempFile);
        }
    }
}
