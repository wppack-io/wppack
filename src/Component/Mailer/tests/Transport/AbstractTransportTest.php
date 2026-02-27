<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\AbstractTransport;

final class AbstractTransportTest extends TestCase
{
    #[Test]
    public function sendCallsDoSend(): void
    {
        $called = false;
        $transport = new class ($called) extends AbstractTransport {
            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test';
            }

            protected function doSend(PhpMailer $phpMailer): void
            {
                $this->called = true;
            }
        };

        $transport->send(new PhpMailer(true));

        self::assertTrue($called);
    }

    #[Test]
    public function sendRethrowsTransportException(): void
    {
        $transport = new class extends AbstractTransport {
            public function getName(): string
            {
                return 'test-failing';
            }

            protected function doSend(PhpMailer $phpMailer): void
            {
                throw new TransportException('Transport failed');
            }
        };

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport failed');
        $transport->send(new PhpMailer(true));
    }

    #[Test]
    public function sendWrapsGenericExceptionInTransportException(): void
    {
        $transport = new class extends AbstractTransport {
            public function getName(): string
            {
                return 'test-generic';
            }

            protected function doSend(PhpMailer $phpMailer): void
            {
                throw new \RuntimeException('Something broke');
            }
        };

        try {
            $transport->send(new PhpMailer(true));
            self::fail('Expected TransportException');
        } catch (TransportException $e) {
            self::assertSame('Something broke', $e->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }
}
