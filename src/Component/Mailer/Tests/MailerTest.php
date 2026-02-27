<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests;

use PHPMailer\PHPMailer\PHPMailer as BasePhpMailer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\TemplatedEmail;
use WpPack\Component\Mailer\TemplateRendererInterface;
use WpPack\Component\Mailer\Transport\NullTransport;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(BasePhpMailer::class)) {
            self::markTestSkipped('PHPMailer is not installed.');
        }

        Mailer::reset();
    }

    protected function tearDown(): void
    {
        Mailer::reset();
    }

    #[Test]
    public function constructWithTransportInterface(): void
    {
        $transport = new NullTransport();
        $mailer = new Mailer($transport);

        self::assertInstanceOf(Mailer::class, $mailer);
    }

    #[Test]
    public function constructWithDsnString(): void
    {
        $mailer = new Mailer('null://default');

        self::assertInstanceOf(Mailer::class, $mailer);
    }

    #[Test]
    public function bootRegistersHooksOnce(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer = new Mailer(new NullTransport());

        $mailer->boot();
        // Second call should be no-op (no duplicate hooks)
        $mailer->boot();

        self::assertTrue(true);
    }

    #[Test]
    public function resetAllowsReboot(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer1 = new Mailer(new NullTransport());
        $mailer1->boot();

        Mailer::reset();

        $mailer2 = new Mailer(new NullTransport());
        $mailer2->boot();

        self::assertTrue(true);
    }

    #[Test]
    public function onPhpMailerInitConfiguresPhpMailer(): void
    {
        $configured = false;
        $transport = new class ($configured) implements TransportInterface {
            public function __construct(private bool &$configured) {}

            public function configure(PhpMailer $phpMailer): void
            {
                $this->configured = true;
            }

            public function __toString(): string
            {
                return 'test://';
            }
        };

        $mailer = new Mailer($transport);
        $wpPackMailer = new PhpMailer(true);

        $mailer->onPhpMailerInit($wpPackMailer);

        self::assertTrue($configured);
    }

    #[Test]
    public function onPhpMailerInitIgnoresRegularPhpMailer(): void
    {
        $configured = false;
        $transport = new class ($configured) implements TransportInterface {
            public function __construct(private bool &$configured) {}

            public function configure(PhpMailer $phpMailer): void
            {
                $this->configured = true;
            }

            public function __toString(): string
            {
                return 'test://';
            }
        };

        $mailer = new Mailer($transport);
        $regularMailer = new PHPMailer(true);

        $mailer->onPhpMailerInit($regularMailer);

        self::assertFalse($configured);
    }

    #[Test]
    public function onWpMailReplacesGlobalPhpMailer(): void
    {
        global $phpmailer;
        $originalMailer = $phpmailer;

        $mailer = new Mailer(new NullTransport());
        $args = [
            'to' => 'user@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
            'headers' => '',
            'attachments' => [],
        ];

        $result = $mailer->onWpMail($args);

        self::assertInstanceOf(PhpMailer::class, $phpmailer);
        self::assertSame($args, $result);

        $phpmailer = $originalMailer;
    }

    #[Test]
    public function sendWithNullTransport(): void
    {
        if (!function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Test Subject')
            ->text('Hello World');

        $sentMessage = $mailer->send($email);

        self::assertSame('sender@example.com', $sentMessage->getEnvelope()->getSender()->address);
        self::assertCount(1, $sentMessage->getEnvelope()->getRecipients());
        self::assertSame('user@example.com', $sentMessage->getEnvelope()->getRecipients()[0]->address);
    }

    #[Test]
    public function sendWithHtmlEmail(): void
    {
        if (!function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('HTML Test')
            ->html('<h1>Hello</h1>')
            ->text('Hello plain');

        $sentMessage = $mailer->send($email);

        self::assertSame('HTML Test', $sentMessage->getEmail()->getSubject());
    }

    #[Test]
    public function sendTemplatedEmailWithoutRendererThrows(): void
    {
        if (!function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer = new Mailer(new NullTransport());
        $email = (new TemplatedEmail())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Templated')
            ->htmlTemplate('email/welcome.html.twig');

        $this->expectException(InvalidArgumentException::class);
        $mailer->send($email);
    }

    #[Test]
    public function sendTemplatedEmailWithRenderer(): void
    {
        if (!function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $renderer = new class implements TemplateRendererInterface {
            public function render(string $template, array $context = []): string
            {
                return '<p>Rendered: ' . $template . '</p>';
            }
        };

        $mailer = new Mailer(new NullTransport());
        $mailer->setTemplateRenderer($renderer);

        $email = (new TemplatedEmail())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Templated')
            ->htmlTemplate('email/welcome.html.twig');

        $sentMessage = $mailer->send($email);

        self::assertSame('<p>Rendered: email/welcome.html.twig</p>', $sentMessage->getEmail()->getHtml());
    }

    #[Test]
    public function sendWithMultipleRecipientTypes(): void
    {
        if (!function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Multi-recipient')
            ->text('Hello');

        $sentMessage = $mailer->send($email);

        self::assertCount(3, $sentMessage->getEnvelope()->getRecipients());
    }
}
