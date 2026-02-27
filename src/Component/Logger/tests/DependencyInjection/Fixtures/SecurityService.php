<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\DependencyInjection\Fixtures;

use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class SecurityService
{
    public function __construct(
        #[LoggerChannel('security')]
        private readonly LoggerInterface $logger,
    ) {}
}
