<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\LoggerDataCollector;

final class LoggerDataCollectorTest extends TestCase
{
    private LoggerDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new LoggerDataCollector();
    }

    protected function tearDown(): void
    {
        // Ensure error handler is always restored
        $this->collector->reset();
    }

    #[Test]
    public function getNameReturnsLogger(): void
    {
        self::assertSame('logger', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsLogs(): void
    {
        self::assertSame('Logs', $this->collector->getLabel());
    }

    #[Test]
    public function logStoresEntries(): void
    {
        $this->collector->log('info', 'Test message');
        $this->collector->log('debug', 'Debug message');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['total_count']);
        self::assertCount(2, $data['logs']);
        self::assertSame('info', $data['logs'][0]['level']);
        self::assertSame('Test message', $data['logs'][0]['message']);
        self::assertSame('app', $data['logs'][0]['channel']);
        self::assertSame('debug', $data['logs'][1]['level']);
        self::assertSame('Debug message', $data['logs'][1]['message']);
    }

    #[Test]
    public function logWithDifferentLevels(): void
    {
        $this->collector->log('error', 'Error occurred');
        $this->collector->log('warning', 'A warning');
        $this->collector->log('info', 'Some info');
        $this->collector->log('critical', 'Critical failure');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(4, $data['total_count']);
        self::assertSame(1, $data['level_counts']['error']);
        self::assertSame(1, $data['level_counts']['warning']);
        self::assertSame(1, $data['level_counts']['info']);
        self::assertSame(1, $data['level_counts']['critical']);
    }

    #[Test]
    public function logWithCustomChannel(): void
    {
        $this->collector->log('info', 'Custom channel message', [], 'custom');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('custom', $data['logs'][0]['channel']);
    }

    #[Test]
    public function logWithContext(): void
    {
        $this->collector->log('info', 'With context', ['key' => 'value']);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(['key' => 'value'], $data['logs'][0]['context']);
    }

    #[Test]
    public function logEntryIncludesFileAndLine(): void
    {
        $this->collector->log('info', 'With file', ['_file' => '/path/to/file.php', '_line' => 42]);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('/path/to/file.php', $data['logs'][0]['file']);
        self::assertSame(42, $data['logs'][0]['line']);
        // _file and _line should be stripped from context
        self::assertArrayNotHasKey('_file', $data['logs'][0]['context']);
        self::assertArrayNotHasKey('_line', $data['logs'][0]['context']);
    }

    #[Test]
    public function captureDeprecationStoresDeprecation(): void
    {
        $this->collector->captureDeprecation('old_function', 'new_function', '5.0');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('deprecation', $data['logs'][0]['level']);
        self::assertStringContainsString('old_function', $data['logs'][0]['message']);
        self::assertStringContainsString('deprecated', $data['logs'][0]['message']);
        self::assertStringContainsString('5.0', $data['logs'][0]['message']);
        self::assertStringContainsString('new_function', $data['logs'][0]['message']);
        self::assertSame('wordpress', $data['logs'][0]['channel']);
        self::assertSame('deprecation', $data['logs'][0]['context']['type']);
    }

    #[Test]
    public function captureDeprecationWithEmptyReplacement(): void
    {
        $this->collector->captureDeprecation('old_function', '', '5.0');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('an alternative', $data['logs'][0]['message']);
    }

    #[Test]
    public function captureDeprecatedHookStoresDeprecation(): void
    {
        $this->collector->captureDeprecatedHook('old_hook', 'new_hook', '5.0', '');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('deprecation', $data['logs'][0]['level']);
        self::assertStringContainsString('old_hook', $data['logs'][0]['message']);
        self::assertStringContainsString('deprecated', $data['logs'][0]['message']);
        self::assertSame('wordpress', $data['logs'][0]['channel']);
        self::assertSame('deprecated_hook', $data['logs'][0]['context']['type']);
    }

    #[Test]
    public function captureDoingItWrongStoresDeprecation(): void
    {
        $this->collector->captureDoingItWrong('some_function', 'You are doing it wrong.', '5.0');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('deprecation', $data['logs'][0]['level']);
        self::assertStringContainsString('some_function', $data['logs'][0]['message']);
        self::assertStringContainsString('incorrectly', $data['logs'][0]['message']);
        self::assertStringContainsString('5.0', $data['logs'][0]['message']);
        self::assertSame('wordpress', $data['logs'][0]['channel']);
        self::assertSame('doing_it_wrong', $data['logs'][0]['context']['type']);
    }

    #[Test]
    public function getBadgeValueReturnsCount(): void
    {
        $this->collector->log('info', 'Message 1');
        $this->collector->log('info', 'Message 2');
        $this->collector->log('info', 'Message 3');

        $this->collector->collect();

        self::assertSame('3', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoLogs(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsRedForErrors(): void
    {
        $this->collector->log('error', 'An error');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForCritical(): void
    {
        $this->collector->log('critical', 'Critical issue');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForAlert(): void
    {
        $this->collector->log('alert', 'Alert');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForEmergency(): void
    {
        $this->collector->log('emergency', 'Emergency');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowForDeprecations(): void
    {
        $this->collector->log('deprecation', 'A deprecation notice');
        $this->collector->log('info', 'Some info');

        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsGreenForWarningsOnly(): void
    {
        $this->collector->log('warning', 'A warning');
        $this->collector->log('info', 'Some info');

        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsGreenForInfoDebugOnly(): void
    {
        $this->collector->log('info', 'Some info');
        $this->collector->log('debug', 'Debug message');

        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenNoLogs(): void
    {
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function collectTruncatesMessagesTo1000Chars(): void
    {
        $longMessage = str_repeat('a', 2000);

        $this->collector->log('info', $longMessage);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1001, mb_strlen($data['logs'][0]['message']));
        self::assertStringEndsWith("\u{2026}", $data['logs'][0]['message']);
    }

    #[Test]
    public function collectLimitsTo200Entries(): void
    {
        for ($i = 0; $i < 250; $i++) {
            $this->collector->log('info', "Message $i");
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertCount(200, $data['logs']);
        self::assertSame(250, $data['total_count']);
    }

    #[Test]
    public function collectCountsDeprecationsCorrectly(): void
    {
        $this->collector->log('deprecation', 'Deprecated 1');
        $this->collector->log('deprecation', 'Deprecated 2');
        $this->collector->log('warning', 'A warning');
        $this->collector->log('info', 'Some info');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['deprecation_count']);
    }

    #[Test]
    public function maskSensitiveContextMasksPasswordKeys(): void
    {
        $this->collector->log('info', 'Test', [
            'username' => 'admin',
            'password' => 'secret123',
            'api_key' => 'sk-abc',
            'access_token' => 'tok-xyz',
            'normal' => 'visible',
        ]);

        $this->collector->collect();
        $data = $this->collector->getData();

        $context = $data['logs'][0]['context'];
        self::assertSame('admin', $context['username']);
        self::assertSame('********', $context['password']);
        self::assertSame('********', $context['api_key']);
        self::assertSame('********', $context['access_token']);
        self::assertSame('visible', $context['normal']);
    }

    #[Test]
    public function maskSensitiveContextMasksNestedKeys(): void
    {
        $this->collector->log('info', 'Test', [
            'user' => [
                'name' => 'John',
                'secret' => 'hidden',
            ],
        ]);

        $this->collector->collect();
        $data = $this->collector->getData();

        $context = $data['logs'][0]['context'];
        self::assertSame('John', $context['user']['name']);
        self::assertSame('********', $context['user']['secret']);
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->log('info', 'Message');
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should yield empty logs
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
        self::assertSame([], $data['logs']);
        self::assertSame([], $data['level_counts']);
    }

    #[Test]
    public function collectCountsErrorLevelsCorrectly(): void
    {
        $this->collector->log('error', 'Error 1');
        $this->collector->log('error', 'Error 2');
        $this->collector->log('critical', 'Critical 1');
        $this->collector->log('alert', 'Alert 1');
        $this->collector->log('emergency', 'Emergency 1');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(5, $data['error_count']);
    }

    #[Test]
    public function logEntryIncludesTimestamp(): void
    {
        $before = microtime(true);
        $this->collector->log('info', 'Timed message');
        $after = microtime(true);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThanOrEqual($before, $data['logs'][0]['timestamp']);
        self::assertLessThanOrEqual($after, $data['logs'][0]['timestamp']);
    }

    #[Test]
    public function registerErrorHandlerCapturesDeprecation(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');

        // Ensure error_reporting includes user-level errors
        $previousLevel = error_reporting(E_ALL);

        $method->invoke($this->collector, E_USER_DEPRECATED, 'Test deprecated function', '/test/file.php', 10);

        error_reporting($previousLevel);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('deprecation', $data['logs'][0]['level']);
        self::assertSame('php', $data['logs'][0]['channel']);
        self::assertStringContainsString('Test deprecated function', $data['logs'][0]['message']);
        self::assertSame('/test/file.php', $data['logs'][0]['file']);
        self::assertSame(10, $data['logs'][0]['line']);
    }

    #[Test]
    public function registerErrorHandlerCapturesWarning(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');

        $previousLevel = error_reporting(E_ALL);

        $method->invoke($this->collector, E_USER_WARNING, 'Test warning', '/test/warn.php', 20);

        error_reporting($previousLevel);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('warning', $data['logs'][0]['level']);
        self::assertSame('php', $data['logs'][0]['channel']);
    }

    #[Test]
    public function registerErrorHandlerCapturesNotice(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');

        $previousLevel = error_reporting(E_ALL);

        $method->invoke($this->collector, E_USER_NOTICE, 'Test notice', '/test/notice.php', 30);

        error_reporting($previousLevel);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('notice', $data['logs'][0]['level']);
        self::assertSame('php', $data['logs'][0]['channel']);
    }

    #[Test]
    public function registerErrorHandlerRespectsAtSuppression(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');

        // Simulate @ suppression by temporarily setting error_reporting to 0
        $previousLevel = error_reporting(0);
        $method->invoke($this->collector, E_USER_WARNING, 'Suppressed error', '/test/file.php', 1);
        error_reporting($previousLevel);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
    }

    #[Test]
    public function restoreErrorHandlerRestoresPrevious(): void
    {
        $this->collector->registerErrorHandler();
        $this->collector->restoreErrorHandler();

        // After restoring, our collector should not capture new errors
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
    }

    #[Test]
    public function handlePhpErrorCapturesRecoverableError(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');
        $oldLevel = error_reporting(E_ALL);

        try {
            $method->invoke($this->collector, E_RECOVERABLE_ERROR, 'Recoverable error occurred', '/file.php', 10);
        } finally {
            error_reporting($oldLevel);
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        $log = $data['logs'][0];
        self::assertSame('error', $log['level']);
        self::assertSame('E_RECOVERABLE_ERROR', $log['context']['_error_type']);
    }

    #[Test]
    public function handlePhpErrorCapturesUnknownErrorType(): void
    {
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');
        // E_ALL includes all known errors, so set error_reporting to include everything
        $oldLevel = error_reporting(-1);

        try {
            // Use E_COMPILE_ERROR (64) which is not in the match statement
            $method->invoke($this->collector, E_COMPILE_ERROR, 'Unknown error type', '/file.php', 10);
        } finally {
            error_reporting($oldLevel);
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        $log = $data['logs'][0];
        self::assertSame('warning', $log['level']);
        self::assertSame('E_UNKNOWN', $log['context']['_error_type']);
    }

    #[Test]
    public function handlePhpErrorCallsPreviousHandler(): void
    {
        $previousCalled = false;
        $capturedErrno = 0;

        // Set previousErrorHandler directly via reflection
        $method = new \ReflectionMethod($this->collector, 'handlePhpError');
        $ref = new \ReflectionProperty($this->collector, 'previousErrorHandler');
        $ref->setValue($this->collector, function (int $errno) use (&$previousCalled, &$capturedErrno): bool {
            $previousCalled = true;
            $capturedErrno = $errno;

            return false;
        });

        $oldLevel = error_reporting(E_ALL);

        try {
            $method->invoke($this->collector, E_USER_WARNING, 'test warning', '/file.php', 42);
        } finally {
            error_reporting($oldLevel);
            $ref->setValue($this->collector, null);
        }

        self::assertTrue($previousCalled, 'Previous error handler should have been called');
        self::assertSame(E_USER_WARNING, $capturedErrno);
    }
}
