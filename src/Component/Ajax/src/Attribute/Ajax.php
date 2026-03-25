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

namespace WpPack\Component\Ajax\Attribute;

use WpPack\Component\Ajax\Access;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Ajax
{
    public function __construct(
        public readonly string $action,
        public readonly Access $access = Access::Public,
        public readonly ?string $checkReferer = null,
        public readonly int $priority = 10,
    ) {}
}
