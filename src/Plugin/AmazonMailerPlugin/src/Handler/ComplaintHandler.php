<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;
use WpPack\Plugin\AmazonMailerPlugin\SuppressionList;

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
