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

namespace WPPack\Component\DependencyInjection\Tests\Fixtures\Config;

use WPPack\Component\DependencyInjection\Attribute\Constant;

final readonly class ConstantConfig
{
    public function __construct(
        #[Constant('TEST_CONSTANT_VALUE')]
        public int $value = 0,
    ) {}
}
