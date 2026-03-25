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

namespace WpPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\TemplatedEmail;
use WpPack\Component\Mailer\TemplateRendererInterface;
use WpPack\Component\Mailer\Transport\NullTransport;

final class MailerTest extends TestCase
{
    protected function setUp(): void
    {
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
    public function constructWithCustomPhpMailer(): void
    {
        $phpMailer = new PhpMailer(true);
        $mailer = new Mailer(new NullTransport(), $phpMailer);

        self::assertInstanceOf(Mailer::class, $mailer);
    }

    #[Test]
    public function bootRegistersHooksOnce(): void
    {
        $mailer = new Mailer(new NullTransport());

        $mailer->boot();
        // Second call should be no-op (no duplicate hooks)
        $mailer->boot();

        self::assertTrue(true);
    }

    #[Test]
    public function resetAllowsReboot(): void
    {
        $mailer1 = new Mailer(new NullTransport());
        $mailer1->boot();

        Mailer::reset();

        $mailer2 = new Mailer(new NullTransport());
        $mailer2->boot();

        self::assertTrue(true);
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
    public function onWpMailUsesInjectedPhpMailer(): void
    {
        global $phpmailer;
        $originalMailer = $phpmailer;

        $customPhpMailer = new PhpMailer(true);
        $mailer = new Mailer(new NullTransport(), $customPhpMailer);

        $mailer->onWpMail([
            'to' => 'user@example.com',
            'subject' => 'Test',
            'message' => 'Hello',
            'headers' => '',
            'attachments' => [],
        ]);

        self::assertSame($customPhpMailer, $phpmailer);

        $phpmailer = $originalMailer;
    }

    #[Test]
    public function sendWithNullTransport(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Test Subject')
            ->text('Hello World');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertArrayHasKey('sent_message', $succeededData);
        $sentMessage = $succeededData['sent_message'];
        self::assertInstanceOf(\WpPack\Component\Mailer\SentMessage::class, $sentMessage);
        self::assertSame('sender@example.com', $sentMessage->getEnvelope()->getSender()->address);
        self::assertCount(1, $sentMessage->getEnvelope()->getRecipients());
        self::assertSame('user@example.com', $sentMessage->getEnvelope()->getRecipients()[0]->address);
    }

    #[Test]
    public function sendWithHtmlEmail(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('HTML Test')
            ->html('<h1>Hello</h1>')
            ->text('Hello plain');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertSame('HTML Test', $succeededData['sent_message']->getEmail()->getSubject());
    }

    #[Test]
    public function sendTemplatedEmailWithoutRendererThrows(): void
    {
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
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

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

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertSame('<p>Rendered: email/welcome.html.twig</p>', $succeededData['sent_message']->getEmail()->getHtml());
    }

    #[Test]
    public function sendThrowsTransportExceptionOnFailure(): void
    {
        $failingTransport = new class implements \WpPack\Component\Mailer\Transport\TransportInterface {
            public function getName(): string
            {
                return 'failing';
            }

            public function send(PhpMailer $phpMailer): void
            {
                throw new \WpPack\Component\Mailer\Exception\TransportException('Connection refused');
            }
        };

        $mailer = new Mailer($failingTransport);
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Test')
            ->text('Hello');

        $this->expectException(\WpPack\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('Connection refused');
        $mailer->send($email);
    }

    #[Test]
    public function sendFiresWpMailFailedOnFailure(): void
    {
        $failingTransport = new class implements \WpPack\Component\Mailer\Transport\TransportInterface {
            public function getName(): string
            {
                return 'failing';
            }

            public function send(PhpMailer $phpMailer): void
            {
                throw new \WpPack\Component\Mailer\Exception\TransportException('Send failed');
            }
        };

        $failedError = null;
        add_action('wp_mail_failed', static function (\WP_Error $error) use (&$failedError): void {
            $failedError = $error;
        });

        $mailer = new Mailer($failingTransport);
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Failure Test')
            ->text('Hello');

        try {
            $mailer->send($email);
        } catch (\WpPack\Component\Mailer\Exception\TransportException) {
            // Expected
        }

        self::assertInstanceOf(\WP_Error::class, $failedError);
        self::assertSame('wp_mail_failed', $failedError->get_error_code());
        self::assertSame('Send failed', $failedError->get_error_message());
    }

    #[Test]
    public function sendWithMultipleRecipientTypes(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Multi-recipient')
            ->text('Hello');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertCount(3, $succeededData['sent_message']->getEnvelope()->getRecipients());
    }

    #[Test]
    public function sendWithAttachments(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wppack_mailer_test_');
        file_put_contents($file, 'attachment content');

        try {
            $succeededData = null;
            add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
                $succeededData = $data;
            });

            $mailer = new Mailer(new NullTransport());
            $email = (new Email())
                ->from('sender@example.com')
                ->to('user@example.com')
                ->subject('With Attachment')
                ->text('See attached')
                ->attach($file, 'report.pdf', 'application/pdf');

            $mailer->send($email);

            self::assertNotNull($succeededData);
            self::assertSame([$file], $succeededData['attachments']);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function sendWithReturnPath(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Return Path Test')
            ->text('Hello')
            ->returnPath('bounce@example.com');

        $mailer->send($email);

        self::assertNotNull($succeededData);
    }

    #[Test]
    public function sendWithReplyTo(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Reply-To Test')
            ->text('Hello')
            ->replyTo('reply@example.com');

        $mailer->send($email);

        self::assertNotNull($succeededData);
    }

    #[Test]
    public function sendWithCustomHeaders(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Custom Headers')
            ->text('Hello')
            ->addHeader('X-Campaign', 'spring-sale');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertNotEmpty($succeededData['headers']);
    }

    #[Test]
    public function sendWrapsNonTransportException(): void
    {
        $throwingTransport = new class implements \WpPack\Component\Mailer\Transport\TransportInterface {
            public function getName(): string
            {
                return 'throwing';
            }

            public function send(PhpMailer $phpMailer): void
            {
                throw new \RuntimeException('Unexpected error');
            }
        };

        $mailer = new Mailer($throwingTransport);
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Test')
            ->text('Hello');

        $this->expectException(\WpPack\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('Unexpected error');
        $mailer->send($email);
    }

    #[Test]
    public function sendTemplatedEmailRendersTextTemplate(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $renderer = new class implements TemplateRendererInterface {
            public function render(string $template, array $context = []): string
            {
                return 'Rendered: ' . $template;
            }
        };

        $mailer = new Mailer(new NullTransport());
        $mailer->setTemplateRenderer($renderer);

        $email = (new TemplatedEmail())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Text Template')
            ->textTemplate('email/welcome.txt');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        self::assertSame('Rendered: email/welcome.txt', $succeededData['sent_message']->getEmail()->getText());
    }

    #[Test]
    public function sendWithInlineAttachment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wppack_mailer_inline_');
        file_put_contents($file, 'image-data');

        try {
            $succeededData = null;
            add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
                $succeededData = $data;
            });

            $mailer = new Mailer(new NullTransport());
            $email = (new Email())
                ->from('sender@example.com')
                ->to('user@example.com')
                ->subject('Inline Image')
                ->html('<img src="cid:logo">')
                ->embed($file, 'logo', 'image/png');

            $mailer->send($email);

            self::assertNotNull($succeededData);
            $attachments = $succeededData['sent_message']->getEmail()->getAttachments();
            self::assertCount(1, $attachments);
            self::assertTrue($attachments[0]->inline);
            self::assertSame('logo', $attachments[0]->name);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function sendAppliesWpMailFromFilter(): void
    {
        $filter = static fn(): string => 'filtered@example.com';
        add_filter('wp_mail_from', $filter);

        try {
            $initData = null;
            add_action('phpmailer_init', static function (PhpMailer $pm) use (&$initData): void {
                $initData = ['From' => $pm->From];
            });

            $mailer = new Mailer(new NullTransport());
            $email = (new Email())
                ->from('original@example.com')
                ->to('user@example.com')
                ->subject('From Filter Test')
                ->text('Hello');

            $mailer->send($email);

            self::assertNotNull($initData);
            self::assertSame('filtered@example.com', $initData['From']);
        } finally {
            remove_filter('wp_mail_from', $filter);
        }
    }

    #[Test]
    public function sendAppliesWpMailFromNameFilter(): void
    {
        $filter = static fn(): string => 'Filtered Name';
        add_filter('wp_mail_from_name', $filter);

        try {
            $initData = null;
            add_action('phpmailer_init', static function (PhpMailer $pm) use (&$initData): void {
                $initData = ['FromName' => $pm->FromName];
            });

            $mailer = new Mailer(new NullTransport());
            $email = (new Email())
                ->from('sender@example.com', 'Original Name')
                ->to('user@example.com')
                ->subject('FromName Filter Test')
                ->text('Hello');

            $mailer->send($email);

            self::assertNotNull($initData);
            self::assertSame('Filtered Name', $initData['FromName']);
        } finally {
            remove_filter('wp_mail_from_name', $filter);
        }
    }

    #[Test]
    public function sendEmailFromOverridesDefaultButFilterStillApplies(): void
    {
        $filter = static fn(string $from): string => 'forced@example.com';
        add_filter('wp_mail_from', $filter);

        try {
            $initData = null;
            add_action('phpmailer_init', static function (PhpMailer $pm) use (&$initData): void {
                $initData = ['From' => $pm->From];
            });

            $mailer = new Mailer(new NullTransport());
            $email = (new Email())
                ->from('custom@example.com')
                ->to('user@example.com')
                ->subject('Override + Filter Test')
                ->text('Hello');

            $mailer->send($email);

            self::assertNotNull($initData);
            self::assertSame('forced@example.com', $initData['From']);
        } finally {
            remove_filter('wp_mail_from', $filter);
        }
    }

    #[Test]
    public function sendWithHtmlOnlyNoTextBody(): void
    {
        $succeededData = null;
        add_action('wp_mail_succeeded', static function (array $data) use (&$succeededData): void {
            $succeededData = $data;
        });

        $mailer = new Mailer(new NullTransport());
        $email = (new Email())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('HTML Only')
            ->html('<h1>Hello</h1>');

        $mailer->send($email);

        self::assertNotNull($succeededData);
        $sentEmail = $succeededData['sent_message']->getEmail();
        self::assertSame('<h1>Hello</h1>', $sentEmail->getHtml());
        self::assertNull($sentEmail->getText());
    }
}
