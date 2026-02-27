<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

final class NativeTransport implements TransportInterface
{
    public function configure(PhpMailer $phpMailer): void
    {
        // No-op: use whatever WordPress and other plugins configured.
    }

    public function __toString(): string
    {
        return 'native://default';
    }
}
