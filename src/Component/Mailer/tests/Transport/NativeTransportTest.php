<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\NativeTransport;

final class NativeTransportTest extends TestCase
{
    #[Test]
    public function getNameReturnsMail(): void
    {
        $transport = new NativeTransport();

        self::assertSame('mail', $transport->getName());
    }

}
