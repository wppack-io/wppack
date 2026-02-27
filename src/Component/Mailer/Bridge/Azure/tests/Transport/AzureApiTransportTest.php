<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\Response;
use WpPack\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class AzureApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }
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
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    body: json_encode(['id' => 'azure-msg-id-123']),
                );
            }
        };

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
            '2024-07-01-preview',
            $httpClient,
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertSame('<azure-msg-id-123>', $phpMailer->getLastMessageID());

        $payload = json_decode($httpClient->capturedBody, true);
        self::assertArrayHasKey('senderAddress', $payload);
        self::assertSame('sender@example.com', $payload['senderAddress']);
        self::assertArrayHasKey('recipients', $payload);
        self::assertArrayHasKey('content', $payload);
    }

    #[Test]
    public function sendThrowsOnEmptyMessageId(): void
    {
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public function post(string $url, array $options = []): Response
            {
                return new Response(
                    statusCode: 202,
                    body: json_encode(['status' => 'Running']),
                );
            }
        };

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
            '2024-07-01-preview',
            $httpClient,
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendThrowsOnApiError(): void
    {
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public function post(string $url, array $options = []): Response
            {
                return new Response(statusCode: 400, body: '{"error":"Bad Request"}');
            }
        };

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
            '2024-07-01-preview',
            $httpClient,
        );
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('status 400');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendWithCcAndBcc(): void
    {
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    body: json_encode(['id' => 'azure-cc-bcc-id']),
                );
            }
        };

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
            '2024-07-01-preview',
            $httpClient,
        );
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addCC('cc@example.com', 'CC User');
        $phpMailer->addBCC('bcc@example.com', 'BCC User');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        $recipients = $payload['recipients'];
        self::assertArrayHasKey('cc', $recipients);
        self::assertArrayHasKey('bcc', $recipients);
        self::assertSame('cc@example.com', $recipients['cc'][0]['address']);
        self::assertSame('bcc@example.com', $recipients['bcc'][0]['address']);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    body: json_encode(['id' => 'azure-reply-id']),
                );
            }
        };

        $transport = new AzureApiTransport(
            'test.communication.azure.com',
            base64_encode('test-key'),
            '2024-07-01-preview',
            $httpClient,
        );
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply@example.com', 'Reply');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
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
