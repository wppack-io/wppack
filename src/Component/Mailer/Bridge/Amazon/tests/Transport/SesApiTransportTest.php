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

namespace WPPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\Result\SendEmailResponse;
use AsyncAws\Ses\SesClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;
use WPPack\Component\Mailer\PhpMailer;

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

        self::assertSame('ses+api', $transport->getName());
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

        $this->expectException(\WPPack\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendWithBccRecipients(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-bcc');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addBCC('bcc@example.com', 'BCC User');

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
        self::assertSame('<msg-bcc>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function sendWithMultipleReplyTo(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-multi-reply');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addReplyTo('reply1@example.com', 'Reply1');
        $phpMailer->addReplyTo('reply2@example.com', 'Reply2');

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
    }

    #[Test]
    public function sendWithCcRecipients(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-cc');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->addCC('cc@example.com', 'CC User');

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
    }

    #[Test]
    public function sendWithHtmlContent(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('msg-html');

                return $response;
            });

        $transport = new SesApiTransport($sesClient);
        $phpMailer = $this->createConfiguredPhpMailer();
        $phpMailer->isHTML(true);
        $phpMailer->Body = '<h1>Hello</h1>';
        $phpMailer->AltBody = 'Hello';

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
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
