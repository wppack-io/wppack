<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\WpPackPhpMailer;

final class NativeTransport implements TransportInterface
{
    public function configure(WpPackPhpMailer $phpMailer): void
    {
        // No-op: use whatever WordPress and other plugins configured.
    }

    public function __toString(): string
    {
        return 'native://default';
    }
}
