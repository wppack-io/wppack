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

namespace WPPack\Component\Messenger\Bridge\Sqs\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Messenger\Bridge\Sqs\Handler\SqsEventHandler;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Messenger\Serializer\SerializerInterface;
use WPPack\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(SqsEventHandler::class)]
final class SqsEventHandlerTest extends TestCase
{
    private string $wordpressPath;

    protected function setUp(): void
    {
        // Create a temp directory with a fake wp-load.php
        $this->wordpressPath = sys_get_temp_dir() . '/wppack-test-' . uniqid();
        mkdir($this->wordpressPath, 0777, true);
        file_put_contents($this->wordpressPath . '/wp-load.php', '<?php // noop');
    }

    protected function tearDown(): void
    {
        @unlink($this->wordpressPath . '/wp-load.php');
        @rmdir($this->wordpressPath);
    }

    #[Test]
    public function processesMessageSuccessfully(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('decode')->willReturn($envelope);

        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $msg, array $stamps) use (&$dispatched, $envelope) {
                $dispatched = ['message' => $msg, 'stamps' => $stamps];

                return $envelope;
            });

        $handler = new SqsEventHandler($this->wordpressPath, $messageBus, $serializer);

        $result = $handler([
            'Records' => [
                ['messageId' => 'msg-001', 'body' => '{"headers":{"type":"stdClass"},"body":"{}"}'],
            ],
        ]);

        self::assertSame(['batchItemFailures' => []], $result);
        self::assertInstanceOf(\stdClass::class, $dispatched['message']);

        // Should include ReceivedStamp
        $hasReceivedStamp = false;
        foreach ($dispatched['stamps'] as $stamp) {
            if ($stamp instanceof ReceivedStamp) {
                $hasReceivedStamp = true;
                self::assertSame('sqs', $stamp->transportName);
            }
        }
        self::assertTrue($hasReceivedStamp, 'Expected ReceivedStamp in dispatched stamps');
    }

    #[Test]
    public function partialBatchFailureReportsFailedMessages(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('decode')->willReturn($envelope);

        $callCount = 0;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturnCallback(function () use (&$callCount, $envelope) {
                $callCount++;
                if ($callCount === 2) {
                    throw new \RuntimeException('Handler failed');
                }

                return $envelope;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $handler = new SqsEventHandler($this->wordpressPath, $messageBus, $serializer, $logger);

        $result = $handler([
            'Records' => [
                ['messageId' => 'msg-001', 'body' => '{"headers":{},"body":"{}"}'],
                ['messageId' => 'msg-002', 'body' => '{"headers":{},"body":"{}"}'],
                ['messageId' => 'msg-003', 'body' => '{"headers":{},"body":"{}"}'],
            ],
        ]);

        self::assertCount(1, $result['batchItemFailures']);
        self::assertSame('msg-002', $result['batchItemFailures'][0]['itemIdentifier']);
    }

    #[Test]
    public function emptyRecordsReturnsNoBatchFailures(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new SqsEventHandler($this->wordpressPath, $messageBus, $serializer);

        $result = $handler(['Records' => []]);

        self::assertSame(['batchItemFailures' => []], $result);
    }

    #[Test]
    public function missingRecordsKeyReturnsNoBatchFailures(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new SqsEventHandler($this->wordpressPath, $messageBus, $serializer);

        $result = $handler([]);

        self::assertSame(['batchItemFailures' => []], $result);
    }

    #[Test]
    public function throwsWhenWordpressPathInvalid(): void
    {
        // Reset the static $booted flag so bootstrap() actually runs
        $ref = new \ReflectionClass(SqsEventHandler::class);
        $prop = $ref->getProperty('booted');
        $prop->setValue(null, false);

        $serializer = $this->createMock(SerializerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $handler = new SqsEventHandler('/nonexistent/path', $messageBus, $serializer);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('WordPress bootstrap file not found');

            $handler(['Records' => [['messageId' => 'msg-001', 'body' => '{}']]]);
        } finally {
            // Restore booted state so other tests aren't affected
            $prop->setValue(null, true);
        }
    }
}
