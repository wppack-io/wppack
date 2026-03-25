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

final readonly class SesBounceMessage
{
    /**
     * @param list<string> $bouncedRecipients
     */
    public function __construct(
        public string $messageId,
        public string $bounceType,
        public string $bounceSubType,
        public array $bouncedRecipients,
        public \DateTimeImmutable $timestamp,
    ) {}
}
