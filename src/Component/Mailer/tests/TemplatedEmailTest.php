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

namespace WPPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\TemplatedEmail;

final class TemplatedEmailTest extends TestCase
{
    #[Test]
    public function htmlTemplate(): void
    {
        $email = (new TemplatedEmail())
            ->htmlTemplate('emails/welcome.html.php')
            ->context(['name' => 'John']);

        self::assertSame('emails/welcome.html.php', $email->getHtmlTemplate());
        self::assertSame(['name' => 'John'], $email->getContext());
    }

    #[Test]
    public function textTemplate(): void
    {
        $email = (new TemplatedEmail())
            ->textTemplate('emails/welcome.txt.php');

        self::assertSame('emails/welcome.txt.php', $email->getTextTemplate());
    }

    #[Test]
    public function bothTemplates(): void
    {
        $email = (new TemplatedEmail())
            ->htmlTemplate('emails/welcome.html.php')
            ->textTemplate('emails/welcome.txt.php')
            ->context(['name' => 'John']);

        self::assertSame('emails/welcome.html.php', $email->getHtmlTemplate());
        self::assertSame('emails/welcome.txt.php', $email->getTextTemplate());
    }

    #[Test]
    public function isInstanceOfEmail(): void
    {
        $email = new TemplatedEmail();

        self::assertInstanceOf(\WPPack\Component\Mailer\Email::class, $email);
    }

    #[Test]
    public function fluentApiChaining(): void
    {
        $email = (new TemplatedEmail())
            ->from('sender@example.com')
            ->to('user@example.com')
            ->subject('Welcome')
            ->htmlTemplate('emails/welcome.html.php')
            ->context(['name' => 'John']);

        self::assertSame('Welcome', $email->getSubject());
        self::assertSame('emails/welcome.html.php', $email->getHtmlTemplate());
    }

    #[Test]
    public function nullTemplateByDefault(): void
    {
        $email = new TemplatedEmail();

        self::assertNull($email->getHtmlTemplate());
        self::assertNull($email->getTextTemplate());
        self::assertSame([], $email->getContext());
    }
}
