<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\Result\SendEmailResponse;
use AsyncAws\Ses\SesClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;
use WpPack\Component\Mailer\PhpMailer;

final class SesApiTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SesClient::class)) {
            self::markTestSkipped('async-aws/ses is not installed.');
        }
    }

    #[Test]
    public function getNameReturnsSesapi(): void
    {
        $transport = new SesApiTransport($this->createMock(SesClient::class));

        self::assertSame('sesapi', $transport->getName());
    }

    #[Test]
    public function sendBuildsSimpleApiPayload(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('test-msg-id');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
        self::assertSame('<test-msg-id>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function sendWithConfigurationSet(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-123');

                return $response;
            });

        $transport = new SesApiTransport($sesClient, 'my-config-set');
        $phpMailer = $this->createConfiguredPhpMailer();

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-reply');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply@example.com', 'Reply');

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
    }

    #[Test]
    public function sendWithAttachments(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-att');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addStringAttachment('file content', 'test.txt', 'base64', 'text/plain');

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
        self::assertSame('<msg-att>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function sendThrowsOnEmptyMessageId(): void
    {
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function () {
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();

        $this->expectException(\WpPack\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
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
