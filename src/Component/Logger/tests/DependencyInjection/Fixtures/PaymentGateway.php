<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Logger\Tests\DependencyInjection\Fixtures;

use Psr\Log\LoggerInterface;
use WPPack\Component\Logger\Attribute\LoggerChannel;

final class PaymentGateway
{
    public function __construct(
        #[LoggerChannel('payment')]
        private readonly LoggerInterface $logger,
    ) {}
}
