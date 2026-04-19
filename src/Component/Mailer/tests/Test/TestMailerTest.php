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

namespace WPPack\Component\Mailer\Tests\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Email;
use WPPack\Component\Mailer\Test\TestMailer;

final class TestMailerTest extends TestCase
{
    #[Test]
    public function sendEmailCapturesMessage(): void
    {
        $testMailer = new TestMailer();
        $email = (new Email())->from('sender@example.com')->to('user@example.com')->subject('Test');

        $sentMessage = $testMailer->sendEmail($email);

        self::assertNotNull($sentMessage->getMessageId());
        self::assertSame($email, $sentMessage->getEmail());
        self::assertCount(1, $testMailer->getSentMessages());
        self::assertSame('sender@example.com', $sentMessage->getEnvelope()->getSender()->address);
    }

    #[Test]
    public function resetClearsSentMessages(): void
    {
        $testMailer = new TestMailer();
        $testMailer->sendEmail((new Email())->from('sender@example.com')->to('user@example.com'));
        $testMailer->sendEmail((new Email())->from('sender@example.com')->to('user2@example.com'));

        self::assertCount(2, $testMailer->getSentMessages());

        $testMailer->reset();

        self::assertCount(0, $testMailer->getSentMessages());
    }
}
