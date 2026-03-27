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

namespace WpPack\Component\Scim\Patch;

final readonly class PatchOperation
{
    /**
     * @param string $op    "add" | "replace" | "remove" (case-insensitive)
     * @param string|null $path  SCIM attribute path
     * @param mixed $value
     */
    public function __construct(
        public string $op,
        public ?string $path,
        public mixed $value,
    ) {}
}
