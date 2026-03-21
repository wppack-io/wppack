<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;

#[AsMessageHandler]
final readonly class BounceHandler
{
    private const OPTION_KEY = 'wppack_ses_suppression_list';

    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(SesBounceMessage $message): void
    {
        $this->logger?->info('SES bounce received', [
            'messageId' => $message->messageId,
            'bounceType' => $message->bounceType,
            'bounceSubType' => $message->bounceSubType,
            'recipients' => $message->bouncedRecipients,
        ]);

        if ($message->bounceType !== 'Permanent') {
            return;
        }

        $this->addToSuppressionList($message->bouncedRecipients);
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
