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

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesNotificationNormalizer;

#[CoversClass(SesNotificationNormalizer::class)]
final class SesNotificationNormalizerTest extends TestCase
{
    private SesNotificationNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SesNotificationNormalizer();
    }

    #[Test]
    public function normalizeBounceNotification(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-001'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounce@example.com'],
                    ['emailAddress' => 'invalid@example.com'],
                ],
                'timestamp' => '2024-01-15T10:30:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertCount(1, $messages);
        self::assertInstanceOf(SesBounceMessage::class, $messages[0]);
        self::assertSame('msg-001', $messages[0]->messageId);
        self::assertSame('Permanent', $messages[0]->bounceType);
        self::assertSame('General', $messages[0]->bounceSubType);
        self::assertSame(['bounce@example.com', 'invalid@example.com'], $messages[0]->bouncedRecipients);
        self::assertSame('2024-01-15', $messages[0]->timestamp->format('Y-m-d'));
    }

    #[Test]
    public function normalizeTransientBounce(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-002'],
            'bounce' => [
                'bounceType' => 'Transient',
                'bounceSubType' => 'MailboxFull',
                'bouncedRecipients' => [
                    ['emailAddress' => 'full@example.com'],
                ],
                'timestamp' => '2024-01-15T12:00:00+09:00',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertCount(1, $messages);
        self::assertInstanceOf(SesBounceMessage::class, $messages[0]);
        self::assertSame('Transient', $messages[0]->bounceType);
        self::assertSame('MailboxFull', $messages[0]->bounceSubType);
    }

    #[Test]
    public function normalizeComplaintNotification(): void
    {
        $notification = [
            'notificationType' => 'Complaint',
            'mail' => ['messageId' => 'msg-003'],
            'complaint' => [
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [
                    ['emailAddress' => 'complainer@example.com'],
                ],
                'timestamp' => '2024-02-20T15:45:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertCount(1, $messages);
        self::assertInstanceOf(SesComplaintMessage::class, $messages[0]);
        self::assertSame('msg-003', $messages[0]->messageId);
        self::assertSame('abuse', $messages[0]->complaintFeedbackType);
        self::assertSame(['complainer@example.com'], $messages[0]->complainedRecipients);
        self::assertSame('2024-02-20', $messages[0]->timestamp->format('Y-m-d'));
    }

    #[Test]
    public function normalizeUnknownNotificationType(): void
    {
        $notification = [
            'notificationType' => 'Delivery',
            'mail' => ['messageId' => 'msg-004'],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeEmptyNotification(): void
    {
        $messages = $this->normalizer->normalize([]);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeMissingMessageId(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => [],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounce@example.com'],
                ],
                'timestamp' => '2024-01-15T10:30:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeBounceWithNoRecipients(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-005'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [],
                'timestamp' => '2024-01-15T10:30:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeComplaintWithNoRecipients(): void
    {
        $notification = [
            'notificationType' => 'Complaint',
            'mail' => ['messageId' => 'msg-006'],
            'complaint' => [
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [],
                'timestamp' => '2024-02-20T15:45:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeBounceWithMissingTimestamp(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-007'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounce@example.com'],
                ],
                'timestamp' => '',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertSame([], $messages);
    }

    #[Test]
    public function normalizeRecipientsWithEmptyEmailSkipped(): void
    {
        $notification = [
            'notificationType' => 'Bounce',
            'mail' => ['messageId' => 'msg-008'],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    ['emailAddress' => ''],
                    ['emailAddress' => 'valid@example.com'],
                ],
                'timestamp' => '2024-01-15T10:30:00.000Z',
            ],
        ];

        $messages = $this->normalizer->normalize($notification);

        self::assertCount(1, $messages);
        self::assertSame(['valid@example.com'], $messages[0]->bouncedRecipients);
    }
}
