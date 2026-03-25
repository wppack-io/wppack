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

namespace WpPack\Component\EventDispatcher\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsEventListener
{
    public function __construct(
        public readonly ?string $event = null,
        public readonly ?string $method = null,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1,
    ) {}
}
