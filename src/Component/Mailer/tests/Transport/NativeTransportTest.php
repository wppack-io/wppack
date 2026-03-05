<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\TransportException;
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

    #[Test]
    public function sendThrowsTransportExceptionWhenNativePostSendReturnsFalse(): void
    {
        $phpMailer = new class (true) extends PhpMailer {
            public function nativePostSend(): bool
            {
                $this->ErrorInfo = 'mail() returned failure';

                return false;
            }
        };

        $transport = new NativeTransport();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Native send failed: mail() returned failure');
        $transport->send($phpMailer);
    }

    #[Test]
    public function sendRethrowsTransportException(): void
    {
        $original = new TransportException('Original transport error');
        $phpMailer = new class (true, $original) extends PhpMailer {
            public function __construct(
                bool $exceptions,
                private readonly TransportException $exception,
            ) {
                parent::__construct($exceptions);
            }

            public function nativePostSend(): bool
            {
                throw $this->exception;
            }
        };

        $transport = new NativeTransport();

        try {
            $transport->send($phpMailer);
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertSame($original, $e);
            self::assertSame('Original transport error', $e->getMessage());
        }
    }

    #[Test]
    public function sendWrapsGenericThrowable(): void
    {
        $original = new \RuntimeException('Unexpected runtime error');
        $phpMailer = new class (true, $original) extends PhpMailer {
            public function __construct(
                bool $exceptions,
                private readonly \RuntimeException $exception,
            ) {
                parent::__construct($exceptions);
            }

            public function nativePostSend(): bool
            {
                throw $this->exception;
            }
        };

        $transport = new NativeTransport();

        try {
            $transport->send($phpMailer);
            self::fail('Expected TransportException was not thrown');
        } catch (TransportException $e) {
            self::assertStringContainsString('Native send failed: Unexpected runtime error', $e->getMessage());
            self::assertSame($original, $e->getPrevious());
        }
    }
}
