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

namespace WpPack\Component\Role\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class IsGranted
{
    public function __construct(
        public readonly string $attribute,
        public readonly mixed $subject = null,
        public readonly string $message = 'Access Denied.',
        public readonly int $statusCode = 403,
    ) {}
}
