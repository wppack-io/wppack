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

namespace WPPack\Component\Scim\Filter;

final readonly class ComparisonNode implements FilterNode
{
    /**
     * @param string $attributePath e.g. "userName", "emails.value"
     * @param string $operator      eq, ne, co, sw, ew, pr, gt, ge, lt, le
     * @param string|null $value    null for "pr" (present) operator
     */
    public function __construct(
        public string $attributePath,
        public string $operator,
        public ?string $value,
    ) {}
}
