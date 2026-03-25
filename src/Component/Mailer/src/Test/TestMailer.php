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

namespace WpPack\Component\Mailer\Test;

use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Envelope;
use WpPack\Component\Mailer\SentMessage;

final class TestMailer
{
    /** @var SentMessage[] */
    private array $sentMessages = [];

    public function sendEmail(Email $email, ?Envelope $envelope = null): SentMessage
    {
        $envelope ??= Envelope::create($email);
        $sentMessage = new SentMessage($email, $envelope, '<test-' . uniqid() . '>');
        $this->sentMessages[] = $sentMessage;

        return $sentMessage;
    }

    /** @return SentMessage[] */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
    }
}
