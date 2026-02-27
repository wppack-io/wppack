<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\DependencyInjection\Fixtures;

use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class PaymentService
{
    public function __construct(
        #[LoggerChannel('payment')]
        private readonly LoggerInterface $logger,
    ) {}
}
