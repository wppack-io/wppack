<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridApiTransport;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;

final class SendGridApiTransportTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $mockResponse = null;

    private ?string $capturedBody = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (function_exists('add_filter')) {
            add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
        }
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
    public function getNameReturnsSendgridApi(): void
    {
        $transport = new SendGridApiTransport('SG.test-key');

        self::assertSame('sendgrid+api', $transport->getName());
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
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-test-id-123'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertSame('<sg-test-id-123>', $phpMailer->getLastMessageID());

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('personalizations', $payload);
        self::assertArrayHasKey('from', $payload);
        self::assertSame('sender@example.com', $payload['from']['email']);
        self::assertSame('Test Subject', $payload['subject']);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-reply-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply@example.com', 'Reply');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('reply_to', $payload);
        self::assertSame('reply@example.com', $payload['reply_to']['email']);
    }

    #[Test]
    public function sendWithMultipleReplyTo(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-multi-reply-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply1@example.com', 'Reply1');
        $phpMailer->addReplyTo('reply2@example.com', 'Reply2');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('reply_to_list', $payload);
        self::assertCount(2, $payload['reply_to_list']);
    }

    #[Test]
    public function sendWithAttachments(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-att-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addStringAttachment('file content', 'test.txt', 'base64', 'text/plain');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('attachments', $payload);
        self::assertCount(1, $payload['attachments']);
        self::assertSame('test.txt', $payload['attachments'][0]['filename']);
    }

    #[Test]
    public function sendThrowsOnApiError(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => [],
            'body' => '{"errors":[{"message":"Bad Request"}]}',
            'response' => ['code' => 400, 'message' => 'Bad Request'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('status 400');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendThrowsOnMissingMessageId(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => [],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendWithCcAndBcc(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-cc-bcc-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addCC('cc@example.com', 'CC User');
        $phpMailer->addBCC('bcc@example.com', 'BCC User');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        $personalization = $payload['personalizations'][0];
        self::assertArrayHasKey('cc', $personalization);
        self::assertArrayHasKey('bcc', $personalization);
        self::assertSame('cc@example.com', $personalization['cc'][0]['email']);
        self::assertSame('bcc@example.com', $personalization['bcc'][0]['email']);
    }

    #[Test]
    public function sendWithHtmlContent(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-html-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->isHTML(true);
        $phpMailer->Body = '<h1>Hello</h1>';
        $phpMailer->AltBody = 'Hello';

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        $content = $payload['content'];
        self::assertCount(2, $content);
        self::assertSame('text/plain', $content[0]['type']);
        self::assertSame('Hello', $content[0]['value']);
        self::assertSame('text/html', $content[1]['type']);
        self::assertSame('<h1>Hello</h1>', $content[1]['value']);
    }

    #[Test]
    public function sendWithInlineAttachment(): void
    {
        if (!function_exists('wp_json_encode')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->mockResponse = [
            'headers' => ['X-Message-Id' => 'sg-inline-id'],
            'body' => '',
            'response' => ['code' => 202, 'message' => 'Accepted'],
            'cookies' => [],
            'filename' => null,
        ];

        $transport = new SendGridApiTransport('SG.test-key');
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->isHTML(true);
        $phpMailer->Body = '<img src="cid:logo">';
        $phpMailer->addStringEmbeddedImage('image-data', 'logo', 'logo.png', 'base64', 'image/png');

        $transport->send($phpMailer);

        $payload = json_decode((string) $this->capturedBody, true);
        self::assertArrayHasKey('attachments', $payload);
        self::assertCount(1, $payload['attachments']);
        self::assertSame('inline', $payload['attachments'][0]['disposition']);
        self::assertSame('logo', $payload['attachments'][0]['content_id']);
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
