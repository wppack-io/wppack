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

final class SecurityService
{
    public function __construct(
        #[LoggerChannel('security')]
        private readonly LoggerInterface $logger,
    ) {}
}
