<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesComplaintMessage;

#[AsMessageHandler]
final readonly class ComplaintHandler
{
    private const OPTION_KEY = 'wppack_ses_suppression_list';

    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(SesComplaintMessage $message): void
    {
        $this->logger?->info('SES complaint received', [
            'messageId' => $message->messageId,
            'feedbackType' => $message->complaintFeedbackType,
            'recipients' => $message->complainedRecipients,
        ]);

        $this->addToSuppressionList($message->complainedRecipients);
    }

    /**
     * @param list<string> $addresses
     */
    private function addToSuppressionList(array $addresses): void
    {
        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');

        /** @var list<string> $list */
        $list = json_decode($json, true) ?: [];

        $updated = false;
        foreach ($addresses as $address) {
            $normalized = strtolower($address);

            if (!\in_array($normalized, $list, true)) {
                $list[] = $normalized;
                $updated = true;
            }
        }

        if ($updated) {
            update_option(self::OPTION_KEY, json_encode($list, \JSON_THROW_ON_ERROR));
        }
    }
}
