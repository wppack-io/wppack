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

namespace WPPack\Plugin\AmazonMailerPlugin\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;
use WPPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;

#[CoversClass(SesBounceMessage::class)]
#[CoversClass(SesComplaintMessage::class)]
final class SesMessagesTest extends TestCase
{
    #[Test]
    public function sesBounceMessageCarriesAllFields(): void
    {
        $timestamp = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $msg = new SesBounceMessage(
            messageId: 'abc-123',
            bounceType: 'Permanent',
            bounceSubType: 'General',
            bouncedRecipients: ['invalid@example.com', 'also-invalid@example.com'],
            timestamp: $timestamp,
        );

        self::assertSame('abc-123', $msg->messageId);
        self::assertSame('Permanent', $msg->bounceType);
        self::assertSame('General', $msg->bounceSubType);
        self::assertSame(['invalid@example.com', 'also-invalid@example.com'], $msg->bouncedRecipients);
        self::assertSame($timestamp, $msg->timestamp);
    }

    #[Test]
    public function sesBounceMessageAllowsEmptyRecipientList(): void
    {
        $msg = new SesBounceMessage(
            messageId: 'x',
            bounceType: 'Transient',
            bounceSubType: 'MailboxFull',
            bouncedRecipients: [],
            timestamp: new \DateTimeImmutable(),
        );

        self::assertSame([], $msg->bouncedRecipients);
    }

    #[Test]
    public function sesComplaintMessageCarriesAllFields(): void
    {
        $timestamp = new \DateTimeImmutable('2024-02-20T08:00:00+00:00');
        $msg = new SesComplaintMessage(
            messageId: 'complaint-456',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['user@example.com'],
            timestamp: $timestamp,
        );

        self::assertSame('complaint-456', $msg->messageId);
        self::assertSame('abuse', $msg->complaintFeedbackType);
        self::assertSame(['user@example.com'], $msg->complainedRecipients);
        self::assertSame($timestamp, $msg->timestamp);
    }

    #[Test]
    public function messagesAreReadonlyStructuralValueObjects(): void
    {
        $ref = new \ReflectionClass(SesBounceMessage::class);
        self::assertTrue($ref->isReadOnly());
        self::assertTrue($ref->isFinal());

        $ref = new \ReflectionClass(SesComplaintMessage::class);
        self::assertTrue($ref->isReadOnly());
        self::assertTrue($ref->isFinal());
    }
}
