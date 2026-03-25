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

namespace WpPack\Plugin\AmazonMailerPlugin\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Attribute\AsMessageHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesBounceMessage;
use WpPack\Plugin\AmazonMailerPlugin\SuppressionList;

#[AsMessageHandler]
final readonly class BounceHandler
{
    public function __construct(
        private SuppressionList $suppressionList,
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

        $this->suppressionList->add($message->bouncedRecipients);
    }
}
