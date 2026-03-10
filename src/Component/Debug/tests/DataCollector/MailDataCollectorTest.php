<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\MailDataCollector;

final class MailDataCollectorTest extends TestCase
{
    private MailDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MailDataCollector();
    }

    #[Test]
    public function getNameReturnsMail(): void
    {
        self::assertSame('mail', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsMail(): void
    {
        self::assertSame('Mail', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithNoEmailsReturnsDefaults(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['emails']);
        self::assertSame(0, $data['total_count']);
        self::assertSame(0, $data['success_count']);
        self::assertSame(0, $data['failure_count']);
    }

    #[Test]
    public function captureMailAttemptStoresEmail(): void
    {
        $args = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test message body',
            'headers' => '',
            'attachments' => [],
        ];

        $returned = $this->collector->captureMailAttempt($args);

        // Filter must return args unmodified
        self::assertSame($args, $returned);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertCount(1, $data['emails']);
        self::assertSame('pending', $data['emails'][0]['status']);
        self::assertSame('Test Subject', $data['emails'][0]['subject']);
        // Recipient should be masked
        self::assertSame('t***@example.com', $data['emails'][0]['to']);
    }

    #[Test]
    public function captureMailSuccessUpdatesPendingEmail(): void
    {
        $args = [
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'message' => 'World',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->captureMailSuccess([
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'headers' => '',
            'attachments' => [],
        ]);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame(1, $data['success_count']);
        self::assertSame(0, $data['failure_count']);
        self::assertSame('sent', $data['emails'][0]['status']);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoEmails(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsYellowWhenPending(): void
    {
        $args = [
            'to' => 'pending@example.com',
            'subject' => 'Pending',
            'message' => 'Not yet sent',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenAllSent(): void
    {
        $args = [
            'to' => 'success@example.com',
            'subject' => 'Success',
            'message' => 'Sent successfully',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->captureMailSuccess([
            'to' => 'success@example.com',
            'subject' => 'Success',
            'headers' => '',
            'attachments' => [],
        ]);
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedWhenFailure(): void
    {
        $args = [
            'to' => 'fail@example.com',
            'subject' => 'Fail',
            'message' => 'This will fail',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);

        // Simulate failure with an object that has get_error_message()
        $error = new class {
            public function get_error_message(): string
            {
                return 'SMTP connection failed';
            }
        };
        $this->collector->captureMailFailure($error);

        $this->collector->collect();

        self::assertSame('red', $this->collector->getBadgeColor());
        self::assertSame('1', $this->collector->getBadgeValue());

        $data = $this->collector->getData();
        self::assertSame(1, $data['failure_count']);
        self::assertSame('failed', $data['emails'][0]['status']);
        self::assertSame('SMTP connection failed', $data['emails'][0]['error']);
    }

    #[Test]
    public function collectMasksEmailAddresses(): void
    {
        $args = [
            'to' => ['alice@example.com', 'bob@domain.org'],
            'subject' => 'Multi-recipient',
            'message' => 'Hello all',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsArray($data['emails'][0]['to']);
        self::assertSame('a***@example.com', $data['emails'][0]['to'][0]);
        self::assertSame('b***@domain.org', $data['emails'][0]['to'][1]);
    }

    #[Test]
    public function collectTruncatesMessageBody(): void
    {
        $longMessage = str_repeat('x', 1000);

        $args = [
            'to' => 'long@example.com',
            'subject' => 'Long body',
            'message' => $longMessage,
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(500, mb_strlen($data['emails'][0]['message']));
    }

    #[Test]
    public function resetClearsData(): void
    {
        $args = [
            'to' => 'reset@example.com',
            'subject' => 'Reset Test',
            'message' => 'Will be cleared',
            'headers' => '',
            'attachments' => [],
        ];

        $this->collector->captureMailAttempt($args);
        $this->collector->collect();

        self::assertSame(1, $this->collector->getData()['total_count']);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collect should return defaults
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
        self::assertSame([], $data['emails']);
    }
}
