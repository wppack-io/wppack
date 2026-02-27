<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\Response;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridApiTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class SendGridApiTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsSendgridApi(): void
    {
        $transport = new SendGridApiTransport('SG.test-key');

        self::assertSame('sendgridapi', $transport->getName());
    }

    #[Test]
    public function constructorAcceptsOptionalHttpClient(): void
    {
        $transport = new SendGridApiTransport(
            apiKey: 'SG.test-key',
            httpClient: null,
        );

        self::assertInstanceOf(SendGridApiTransport::class, $transport);
    }

    #[Test]
    public function sendBuildsApiPayload(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $capturedBody = null;
        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-test-id-123'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertSame('<sg-test-id-123>', $phpMailer->getLastMessageID());

        $payload = json_decode($httpClient->capturedBody, true);
        self::assertArrayHasKey('personalizations', $payload);
        self::assertArrayHasKey('from', $payload);
        self::assertSame('sender@example.com', $payload['from']['email']);
        self::assertSame('Test Subject', $payload['subject']);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-reply-id'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply@example.com', 'Reply');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        self::assertArrayHasKey('reply_to', $payload);
        self::assertSame('reply@example.com', $payload['reply_to']['email']);
    }

    #[Test]
    public function sendWithMultipleReplyTo(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-multi-reply-id'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply1@example.com', 'Reply1');
        $phpMailer->addReplyTo('reply2@example.com', 'Reply2');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        self::assertArrayHasKey('reply_to_list', $payload);
        self::assertCount(2, $payload['reply_to_list']);
    }

    #[Test]
    public function sendWithAttachments(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-att-id'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addStringAttachment('file content', 'test.txt', 'base64', 'text/plain');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        self::assertArrayHasKey('attachments', $payload);
        self::assertCount(1, $payload['attachments']);
        self::assertSame('test.txt', $payload['attachments'][0]['filename']);
    }

    #[Test]
    public function sendThrowsOnApiError(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public function post(string $url, array $options = []): Response
            {
                return new Response(statusCode: 400, body: '{"errors":[{"message":"Bad Request"}]}');
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('status 400');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendThrowsOnMissingMessageId(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public function post(string $url, array $options = []): Response
            {
                return new Response(statusCode: 202);
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendWithCcAndBcc(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-cc-bcc-id'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addCC('cc@example.com', 'CC User');
        $phpMailer->addBCC('bcc@example.com', 'BCC User');

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        $personalization = $payload['personalizations'][0];
        self::assertArrayHasKey('cc', $personalization);
        self::assertArrayHasKey('bcc', $personalization);
        self::assertSame('cc@example.com', $personalization['cc'][0]['email']);
        self::assertSame('bcc@example.com', $personalization['bcc'][0]['email']);
    }

    #[Test]
    public function sendWithHtmlContent(): void
    {
        if (!function_exists('wp_json_encode') || !function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $httpClient = new class extends HttpClient {
            public ?string $capturedBody = null;

            public function post(string $url, array $options = []): Response
            {
                $this->capturedBody = $options['body'] ?? '';

                return new Response(
                    statusCode: 202,
                    headers: ['X-Message-Id' => 'sg-html-id'],
                );
            }
        };

        $transport = new SendGridApiTransport('SG.test-key', $httpClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->isHTML(true);
        $phpMailer->Body = '<h1>Hello</h1>';
        $phpMailer->AltBody = 'Hello';

        $transport->send($phpMailer);

        $payload = json_decode($httpClient->capturedBody, true);
        $content = $payload['content'];
        self::assertCount(2, $content);
        self::assertSame('text/plain', $content[0]['type']);
        self::assertSame('Hello', $content[0]['value']);
        self::assertSame('text/html', $content[1]['type']);
        self::assertSame('<h1>Hello</h1>', $content[1]['value']);
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
