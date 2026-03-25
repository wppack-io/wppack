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

namespace WpPack\Component\Hook\Attribute;

use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Action extends Hook
{
    public function __construct(string $hook, int $priority = 10)
    {
        parent::__construct($hook, HookType::Action, $priority);
    }
}
