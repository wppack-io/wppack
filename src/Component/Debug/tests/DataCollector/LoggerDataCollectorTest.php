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

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\LoggerDataCollector;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\Test\TestHandler;

final class LoggerDataCollectorTest extends TestCase
{
    private LoggerDataCollector $collector;
    private TestHandler $handler;
    private LoggerFactory $factory;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->factory = new LoggerFactory([$this->handler]);
        $this->collector = new LoggerDataCollector($this->factory);
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
    public function captureDeprecationRoutesToLoggerAsNotice(): void
    {
        $this->collector->captureDeprecation('old_function', 'new_function', '5.0');

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('notice', $records[0]['level']);
        self::assertStringContainsString('old_function', $records[0]['message']);
        self::assertStringContainsString('deprecated', $records[0]['message']);
        self::assertStringContainsString('5.0', $records[0]['message']);
        self::assertStringContainsString('new_function', $records[0]['message']);
        self::assertSame('deprecation', $records[0]['context']['_type']);
        self::assertSame('deprecation', $records[0]['context']['type']);
    }

    #[Test]
    public function captureDeprecationWithEmptyReplacement(): void
    {
        $this->collector->captureDeprecation('old_function', '', '5.0');

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertStringContainsString('an alternative', $records[0]['message']);
    }

    #[Test]
    public function captureDeprecatedHookRoutesToLoggerAsNotice(): void
    {
        $this->collector->captureDeprecatedHook('old_hook', 'new_hook', '5.0', '');

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('notice', $records[0]['level']);
        self::assertStringContainsString('old_hook', $records[0]['message']);
        self::assertStringContainsString('deprecated', $records[0]['message']);
        self::assertSame('deprecation', $records[0]['context']['_type']);
        self::assertSame('deprecated_hook', $records[0]['context']['type']);
    }

    #[Test]
    public function captureDoingItWrongRoutesToLoggerAsNotice(): void
    {
        $this->collector->captureDoingItWrong('some_function', 'You are doing it wrong.', '5.0');

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('notice', $records[0]['level']);
        self::assertStringContainsString('some_function', $records[0]['message']);
        self::assertStringContainsString('incorrectly', $records[0]['message']);
        self::assertStringContainsString('5.0', $records[0]['message']);
        self::assertSame('deprecation', $records[0]['context']['_type']);
        self::assertSame('doing_it_wrong', $records[0]['context']['type']);
    }

    #[Test]
    public function getIndicatorValueReturnsCount(): void
    {
        $this->collector->log('info', 'Message 1');
        $this->collector->log('info', 'Message 2');
        $this->collector->log('info', 'Message 3');

        $this->collector->collect();

        self::assertSame('3', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoLogs(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForErrors(): void
    {
        $this->collector->log('error', 'An error');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForCritical(): void
    {
        $this->collector->log('critical', 'Critical issue');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForAlert(): void
    {
        $this->collector->log('alert', 'Alert');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForEmergency(): void
    {
        $this->collector->log('emergency', 'Emergency');

        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowForDeprecations(): void
    {
        $this->collector->log('notice', 'A deprecation notice', ['_type' => 'deprecation']);
        $this->collector->log('info', 'Some info');

        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForWarningsOnly(): void
    {
        $this->collector->log('warning', 'A warning');
        $this->collector->log('info', 'Some info');

        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForInfoDebugOnly(): void
    {
        $this->collector->log('info', 'Some info');
        $this->collector->log('debug', 'Debug message');

        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenWhenNoLogs(): void
    {
        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
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
        $this->collector->log('notice', 'Deprecated 1', ['_type' => 'deprecation']);
        $this->collector->log('notice', 'Deprecated 2', ['_type' => 'deprecation']);
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
    public function collectCountsDeprecationsFromTypeContext(): void
    {
        // Logger-routed deprecation (notice level + _type context)
        $this->collector->log('notice', 'Deprecated via logger', ['_type' => 'deprecation']);
        // Another Logger-routed deprecation
        $this->collector->log('notice', 'Another deprecation', ['_type' => 'deprecation']);
        // Normal notice (should not count as deprecation)
        $this->collector->log('notice', 'Normal notice');
        // Normal warning (should not count as deprecation)
        $this->collector->log('warning', 'Normal warning');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['deprecation_count']);
    }
}
