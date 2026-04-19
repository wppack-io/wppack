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

namespace WPPack\Plugin\AmazonMailerPlugin\Handler;

use Psr\Log\LoggerInterface;
use WPPack\Component\Messenger\Attribute\AsMessageHandler;
use WPPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;
use WPPack\Plugin\AmazonMailerPlugin\SuppressionList;

#[AsMessageHandler]
final readonly class ComplaintHandler
{
    public function __construct(
        private SuppressionList $suppressionList,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(SesComplaintMessage $message): void
    {
        $this->logger?->info('SES complaint received', [
            'messageId' => $message->messageId,
            'feedbackType' => $message->complaintFeedbackType,
            'recipients' => $message->complainedRecipients,
        ]);

        $this->suppressionList->add($message->complainedRecipients);
    }
}
