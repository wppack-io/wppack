<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

interface TransportInterface
{
    /**
     * Apply transport-specific configuration to PHPMailer.
     * Called within the phpmailer_init action.
     */
    public function configure(PhpMailer $phpMailer): void;

    public function __toString(): string;
}
