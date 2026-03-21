<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;

#[CoversClass(ComplaintHandler::class)]
final class ComplaintHandlerTest extends TestCase
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
    public function complaintAddsToSuppressionList(): void
    {
        $handler = new ComplaintHandler();

        $message = new SesComplaintMessage(
            messageId: 'msg-001',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['complainer@example.com'],
            timestamp: new \DateTimeImmutable('2024-02-20T15:45:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertContains('complainer@example.com', $list);
    }

    #[Test]
    public function duplicateAddressesAreNotAdded(): void
    {
        update_option(self::OPTION_KEY, json_encode(['existing@example.com']));

        $handler = new ComplaintHandler();

        $message = new SesComplaintMessage(
            messageId: 'msg-002',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['existing@example.com', 'new@example.com'],
            timestamp: new \DateTimeImmutable('2024-02-20T16:00:00Z'),
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
        $handler = new ComplaintHandler();

        $message = new SesComplaintMessage(
            messageId: 'msg-003',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['User@Example.COM'],
            timestamp: new \DateTimeImmutable('2024-02-20T17:00:00Z'),
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
            ->with('SES complaint received', self::callback(static function (array $context): bool {
                return $context['messageId'] === 'msg-004'
                    && $context['feedbackType'] === 'abuse';
            }));

        $handler = new ComplaintHandler(logger: $logger);

        $message = new SesComplaintMessage(
            messageId: 'msg-004',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['test@example.com'],
            timestamp: new \DateTimeImmutable('2024-02-20T18:00:00Z'),
        );

        $handler($message);
    }

    #[Test]
    public function multipleRecipientsAllAddedToSuppressionList(): void
    {
        $handler = new ComplaintHandler();

        $message = new SesComplaintMessage(
            messageId: 'msg-005',
            complaintFeedbackType: 'not-spam',
            complainedRecipients: ['user1@example.com', 'user2@example.com', 'user3@example.com'],
            timestamp: new \DateTimeImmutable('2024-02-20T19:00:00Z'),
        );

        $handler($message);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $list */
        $list = json_decode($json, true);

        self::assertCount(3, $list);
        self::assertContains('user1@example.com', $list);
        self::assertContains('user2@example.com', $list);
        self::assertContains('user3@example.com', $list);
    }
}
