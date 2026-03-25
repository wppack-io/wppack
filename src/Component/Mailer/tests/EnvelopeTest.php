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

namespace WpPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Address;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Envelope;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;

final class EnvelopeTest extends TestCase
{
    #[Test]
    public function createFromEmail(): void
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com');

        $envelope = Envelope::create($email);

        self::assertSame('sender@example.com', $envelope->getSender()->address);
        self::assertCount(3, $envelope->getRecipients());
    }

    #[Test]
    public function createFromEmailWithoutFromThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $email = (new Email())->to('to@example.com');
        Envelope::create($email);
    }

    #[Test]
    public function createFromEmailWithoutRecipientsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one recipient');

        $email = (new Email())->from('sender@example.com');
        Envelope::create($email);
    }

    #[Test]
    public function constructWithExplicitValues(): void
    {
        $sender = new Address('sender@example.com');
        $recipients = [new Address('to@example.com')];

        $envelope = new Envelope($sender, $recipients);

        self::assertSame($sender, $envelope->getSender());
        self::assertSame($recipients, $envelope->getRecipients());
    }
}
