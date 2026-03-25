<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\DependencyInjection\Fixtures;

use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class PaymentGateway
{
    public function __construct(
        #[LoggerChannel('payment')]
        private readonly LoggerInterface $logger,
    ) {}
}
