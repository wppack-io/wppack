<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\DependencyInjection\Fixtures;

use Psr\Log\LoggerInterface;

final class PlainService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}
