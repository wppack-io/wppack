<?php

declare(strict_types=1);

namespace WpPack\Tests\Component\Mailer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Address;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Envelope;
use WpPack\Component\Mailer\SentMessage;

final class SentMessageTest extends TestCase
{
    #[Test]
    public function gettersReturnConstructorValues(): void
    {
        $email = (new Email())->from('sender@example.com')->to('to@example.com');
        $envelope = Envelope::create($email);
        $sentMessage = new SentMessage($email, $envelope, '<message-id-123>');

        self::assertSame($email, $sentMessage->getEmail());
        self::assertSame($envelope, $sentMessage->getEnvelope());
        self::assertSame('<message-id-123>', $sentMessage->getMessageId());
    }

    #[Test]
    public function messageIdCanBeNull(): void
    {
        $email = (new Email())->from('sender@example.com')->to('to@example.com');
        $envelope = Envelope::create($email);
        $sentMessage = new SentMessage($email, $envelope);

        self::assertNull($sentMessage->getMessageId());
    }

    #[Test]
    public function envelopeContainsAllRecipients(): void
    {
        $email = (new Email())
            ->from('sender@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com');

        $envelope = Envelope::create($email);
        $sentMessage = new SentMessage($email, $envelope);

        self::assertCount(3, $sentMessage->getEnvelope()->getRecipients());
        self::assertSame('sender@example.com', $sentMessage->getEnvelope()->getSender()->address);
    }
}
