<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class AzureApiTransportTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $mockResponse = null;

    private ?string $capturedBody = null;

    protected function setUp(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        if (function_exists('remove_filter')) {
            remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
        }
        $this->mockResponse = null;
        $this->capturedBody = null;
        parent::tearDown();
    }

    /**
     * @param mixed                $preempt
     * @param array<string, mixed> $parsedArgs
     * @return array<string, mixed>
     */
    public function mockHttpResponse(mixed $preempt, array $parsedArgs, string $url): array
    {
        $this->capturedBody = $parsedArgs['body'] ?? '';

        return $this->mockResponse ?? [
            'headers' => [],
            'body' => '',
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    #[Test]
    public function getNameReturnsAzureApi(): void
    {
        $transport = new AzureApiTransport('test.communication.azure.com', 'dGVzdC1rZXk=');

        self::assertSame('azureapi', $transport->getName());
    }

    #[Test]
    public function constructorAcceptsOptionalHttpClient(): void
    {
        $transport = new AzureApiTransport(
            endpoint: 'test.communication.azure.com',
            accessKey: 'dGVzdC1rZXk=',
            httpClient: null,
        );

        self::assertInstanceOf(AzureApiTransport::class, $transport);
    }

    #[Test]
    public function sendBuildsApiPayload(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode(['id' => 'azure-msg-id-123']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertSame('<azure-msg-id-123>', $phpMailer->getLastMessageID());

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('senderAddress', $payload);
        self::assertSame('sender@example.com', $payload['senderAddress']);
        self::assertArrayHasKey('recipients', $payload);
        self::assertArrayHasKey('content', $payload);
    }

    #[Test]
    public function sendThrowsOnEmptyMessageId(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode(['status' => 'Running']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendThrowsOnApiError(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"error":"Bad Request"}',
            'response' => ['code' => 400, 'message' => 'Bad Request'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('status 400');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendWithCcAndBcc(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode(['id' => 'azure-cc-bcc-id']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
        );
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addCC('cc@example.com', 'CC User');
        $phpMailer->addBCC('bcc@example.com', 'BCC User');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        $recipients = $payload['recipients'];
        self::assertArrayHasKey('cc', $recipients);
        self::assertArrayHasKey('bcc', $recipients);
        self::assertSame('cc@example.com', $recipients['cc'][0]['address']);
        self::assertSame('bcc@example.com', $recipients['bcc'][0]['address']);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        $this->mockResponse = [
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode(['id' => 'azure-reply-id']),
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
        );
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply@example.com', 'Reply');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('replyTo', $payload);
    }

    private function createConfiguredPhpMailer(): PhpMailer
    {
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com', 'Sender');
        $phpMailer->addAddress('user@example.com', 'User');
        $phpMailer->Subject = 'Test Subject';
        $phpMailer->Body = 'Hello World';
        $phpMailer->CharSet = 'UTF-8';

        return $phpMailer;
    }
}
