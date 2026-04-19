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

use WPPack\Component\DependencyInjection\Attribute\Env;

final readonly class EnvConfig
{
    public function __construct(
        #[Env('TEST_ENV_VALUE')]
        public string $value = 'default',
    ) {}
}
