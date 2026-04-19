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

namespace WPPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WPPack\Component\DependencyInjection\Attribute\Autowire;

final class EnvNoDefaultService
{
    public function __construct(
        #[Autowire(env: 'UNDEFINED_ENV_VAR')]
        public readonly string $value,
    ) {}
}
