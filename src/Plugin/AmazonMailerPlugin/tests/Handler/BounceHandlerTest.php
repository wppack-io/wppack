<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;

#[CoversClass(BounceHandler::class)]
final class BounceHandlerTest extends TestCase
{
    private const OPTION_KEY = 'wppack_ses_suppression_list';

    protected function setUp(): void
    {
        delete_option(self::OPTION_KEY);
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_KEY);
    }

    #[Test]
    public function permanentBounceAddsToSuppressionList(): void
    {
        $handler = new BounceHandler();

        $message = new SesBounceMessage(
            messageId: 'msg-001',
            bounceType: 'Permanent',
            bounceSubType: 'General',
            bouncedRecipients: ['bounce@example.com', 'invalid@example.com'],
            timestamp: new \DateTimeImmutable('2024-01-15T10:30:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertContains('bounce@example.com', $list);
        self::assertContains('invalid@example.com', $list);
    }

    #[Test]
    public function transientBounceDoesNotAddToSuppressionList(): void
    {
        $handler = new BounceHandler();

        $message = new SesBounceMessage(
            messageId: 'msg-002',
            bounceType: 'Transient',
            bounceSubType: 'MailboxFull',
            bouncedRecipients: ['full@example.com'],
            timestamp: new \DateTimeImmutable('2024-01-15T12:00:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertSame([], $list);
    }

    #[Test]
    public function duplicateAddressesAreNotAdded(): void
    {
        update_option(self::OPTION_KEY, json_encode(['existing@example.com']));

        $handler = new BounceHandler();

        $message = new SesBounceMessage(
            messageId: 'msg-003',
            bounceType: 'Permanent',
            bounceSubType: 'General',
            bouncedRecipients: ['existing@example.com', 'new@example.com'],
            timestamp: new \DateTimeImmutable('2024-01-15T14:00:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertCount(2, $list);
        self::assertContains('existing@example.com', $list);
        self::assertContains('new@example.com', $list);
    }

    #[Test]
    public function addressesAreNormalizedToLowerCase(): void
    {
        $handler = new BounceHandler();

        $message = new SesBounceMessage(
            messageId: 'msg-004',
            bounceType: 'Permanent',
            bounceSubType: 'General',
            bouncedRecipients: ['User@Example.COM'],
            timestamp: new \DateTimeImmutable('2024-01-15T16:00:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertSame(['user@example.com'], $list);
    }

    #[Test]
    public function logsWhenLoggerProvided(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('SES bounce received', self::callback(static function (array $context): bool {
                return $context['messageId'] === 'msg-005'
                    && $context['bounceType'] === 'Permanent';
            }));

        $handler = new BounceHandler(logger: $logger);

        $message = new SesBounceMessage(
            messageId: 'msg-005',
            bounceType: 'Permanent',
            bounceSubType: 'General',
            bouncedRecipients: ['test@example.com'],
            timestamp: new \DateTimeImmutable('2024-01-15T18:00:00Z'),
        );

        $handler($message);
    }
}
