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

    #[Test]
    public function sendDelegatesToNativePostSend(): void
    {
        $called = false;
        $phpMailer = new class (true) extends PhpMailer {
            public function __construct(
                bool $exceptions,
                private bool &$called = false,
            ) {
                parent::__construct($exceptions);
            }

            public function setCalled(bool &$called): void
            {
                $this->called = &$called;
            }

            public function nativePostSend(): bool
            {
                $this->called = true;

                return true;
            }
        };
        $phpMailer->setCalled($called);

        $transport = new NativeTransport();
        $transport->send($phpMailer);

        self::assertTrue($called);
    }
}
