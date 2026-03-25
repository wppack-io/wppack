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

namespace WpPack\Component\Mailer\Bridge\Amazon\Tests\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\Result\SendEmailResponse;
use AsyncAws\Ses\SesClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport;
use WpPack\Component\Mailer\PhpMailer;

final class SesHttpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SesClient::class)) {
            self::markTestSkipped('async-aws/ses is not installed.');
        }
    }

    #[Test]
    public function getNameReturnsSesHttps(): void
    {
        $transport = new SesHttpTransport($this->createMock(SesClient::class));

        self::assertSame('ses+https', $transport->getName());
    }

    #[Test]
    public function sendUsesRawMimeContent(): void
    {
        $capturedRequest = null;
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function (SendEmailRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('raw-msg-id');

                return $response;
            });

        $transport = new SesHttpTransport($sesClient);
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com');
        $phpMailer->addAddress('user@example.com');
        $phpMailer->Subject = 'Raw Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->preSend();

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
        self::assertSame('<raw-msg-id>', $phpMailer->getLastMessageID());
    }

    #[Test]
    public function sendDoesNotDoubleWrapMessageId(): void
    {
        $sesClient = $this->createMock(SesClient::class);
        $sesClient->method('sendEmail')
            ->willReturnCallback(function () {
                $response = $this->createMock(SendEmailResponse::class);
                $response->method('getMessageId')->willReturn('<already-wrapped>');

                return $response;
            });

        $transport = new SesHttpTransport($sesClient);
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com');
        $phpMailer->addAddress('user@example.com');
        $phpMailer->Subject = 'Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->preSend();

        $transport->send($phpMailer);

        self::assertSame('<already-wrapped>', $phpMailer->getLastMessageID());
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
                $response->method('getMessageId')->willReturn('cfg-msg');

                return $response;
            });

        $transport = new SesHttpTransport($sesClient, 'my-config-set');
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com');
        $phpMailer->addAddress('user@example.com');
        $phpMailer->Subject = 'Config Set Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->preSend();

        $transport->send($phpMailer);

        self::assertNotNull($capturedRequest);
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

        $transport = new SesHttpTransport($sesClient);
        $phpMailer = new PhpMailer(true);
        $phpMailer->setFrom('sender@example.com');
        $phpMailer->addAddress('user@example.com');
        $phpMailer->Subject = 'Test';
        $phpMailer->Body = 'Hello';
        $phpMailer->preSend();

        $this->expectException(\WpPack\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('no message ID');
        $transport->send($phpMailer);
    }
}
