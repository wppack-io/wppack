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

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WpPack\Component\Option\OptionManager;
use WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;
use WpPack\Plugin\AmazonMailerPlugin\SuppressionList;

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
        $handler = new ComplaintHandler(new SuppressionList(new OptionManager()));

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
    public function logsWhenLoggerProvided(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('SES complaint received', self::callback(static function (array $context): bool {
                return $context['messageId'] === 'msg-004'
                    && $context['feedbackType'] === 'abuse';
            }));

        $handler = new ComplaintHandler(new SuppressionList(new OptionManager()), logger: $logger);

        $message = new SesComplaintMessage(
            messageId: 'msg-004',
            complaintFeedbackType: 'abuse',
            complainedRecipients: ['test@example.com'],
            timestamp: new \DateTimeImmutable('2024-02-20T18:00:00Z'),
        );

        $handler($message);
    }
}
