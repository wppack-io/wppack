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

namespace WpPack\Plugin\AmazonMailerPlugin\Message;

final readonly class SesComplaintMessage
{
    /**
     * @param list<string> $complainedRecipients
     */
    public function __construct(
        public string $messageId,
        public string $complaintFeedbackType,
        public array $complainedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}
