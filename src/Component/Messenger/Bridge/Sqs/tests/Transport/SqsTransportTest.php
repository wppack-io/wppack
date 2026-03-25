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

namespace WpPack\Component\Messenger\Bridge\Sqs\Tests\Transport;

use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\Result\SendMessageResult;
use AsyncAws\Sqs\SqsClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Bridge\Sqs\Transport\SqsTransport;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\TransportException;
use WpPack\Component\Messenger\Serializer\SerializerInterface;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Stamp\SentStamp;

#[CoversClass(SqsTransport::class)]
final class SqsTransportTest extends TestCase
{
    private const QUEUE_URL = 'https://sqs.ap-northeast-1.amazonaws.com/123456789/test-queue';

    protected function setUp(): void
    {
        if (!class_exists(SqsClient::class)) {
            self::markTestSkipped('async-aws/sqs is not installed.');
        }
    }

    #[Test]
    public function getNameReturnsConfiguredName(): void
    {
        $transport = new SqsTransport(
            $this->createMock(SqsClient::class),
            $this->createMock(SerializerInterface::class),
            self::QUEUE_URL,
        );

        self::assertSame('sqs', $transport->getName());
    }

    #[Test]
    public function getNameReturnsCustomName(): void
    {
        $transport = new SqsTransport(
            $this->createMock(SqsClient::class),
            $this->createMock(SerializerInterface::class),
            self::QUEUE_URL,
            'my-sqs',
        );

        self::assertSame('my-sqs', $transport->getName());
    }

    #[Test]
    public function sendEncodesAndSendsMessage(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')
            ->with($envelope)
            ->willReturn(['headers' => ['type' => 'stdClass'], 'body' => '{}']);

        $capturedRequest = null;
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willReturnCallback(function (SendMessageRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $result = $this->createMock(SendMessageResult::class);
                $result->method('getMessageId')->willReturn('msg-001');

                return $result;
            });

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);
        $resultEnvelope = $transport->send($envelope);

        self::assertNotNull($capturedRequest);
        self::assertSame(self::QUEUE_URL, $capturedRequest->getQueueUrl());

        $body = json_decode($capturedRequest->getMessageBody(), true);
        self::assertSame('stdClass', $body['headers']['type']);
        self::assertSame('{}', $body['body']);

        // No delay should be set
        self::assertNull($capturedRequest->getDelaySeconds());
    }

    #[Test]
    public function sendAppliesDelayFromDelayStamp(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message, [new DelayStamp(5000)]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $capturedRequest = null;
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willReturnCallback(function (SendMessageRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $result = $this->createMock(SendMessageResult::class);
                $result->method('getMessageId')->willReturn('msg-002');

                return $result;
            });

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);
        $transport->send($envelope);

        self::assertNotNull($capturedRequest);
        self::assertSame(5, $capturedRequest->getDelaySeconds());
    }

    #[Test]
    public function sendCapsDelayAtSqsMaximum(): void
    {
        // 1,800,000 ms = 1800 seconds, should be capped to 900
        $message = new \stdClass();
        $envelope = Envelope::wrap($message, [new DelayStamp(1_800_000)]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $capturedRequest = null;
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willReturnCallback(function (SendMessageRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $result = $this->createMock(SendMessageResult::class);
                $result->method('getMessageId')->willReturn('msg-003');

                return $result;
            });

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);
        $transport->send($envelope);

        self::assertNotNull($capturedRequest);
        self::assertSame(900, $capturedRequest->getDelaySeconds());
    }

    #[Test]
    public function sendCeilsMillisecondsToSeconds(): void
    {
        // 1500 ms should ceil to 2 seconds
        $message = new \stdClass();
        $envelope = Envelope::wrap($message, [new DelayStamp(1500)]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $capturedRequest = null;
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willReturnCallback(function (SendMessageRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $result = $this->createMock(SendMessageResult::class);
                $result->method('getMessageId')->willReturn('msg-004');

                return $result;
            });

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);
        $transport->send($envelope);

        self::assertNotNull($capturedRequest);
        self::assertSame(2, $capturedRequest->getDelaySeconds());
    }

    #[Test]
    public function sendAddsSentStamp(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willReturnCallback(function () {
                $result = $this->createMock(SendMessageResult::class);
                $result->method('getMessageId')->willReturn('msg-005');

                return $result;
            });

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL, 'my-sqs');
        $resultEnvelope = $transport->send($envelope);

        $sentStamp = $resultEnvelope->last(SentStamp::class);
        self::assertNotNull($sentStamp);
        self::assertSame('my-sqs', $sentStamp->transportName);
    }

    #[Test]
    public function sendThrowsTransportExceptionOnFailure(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to send message to SQS queue');
        $transport->send($envelope);
    }

    #[Test]
    public function sendPreservesOriginalExceptionAsPrevious(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['headers' => [], 'body' => '{}']);

        $originalException = new \RuntimeException('Network error');
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->method('sendMessage')
            ->willThrowException($originalException);

        $transport = new SqsTransport($sqsClient, $serializer, self::QUEUE_URL);

        try {
            $transport->send($envelope);
            self::fail('Expected TransportException was not thrown.');
        } catch (TransportException $e) {
            self::assertSame($originalException, $e->getPrevious());
        }
    }
}
