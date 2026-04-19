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

namespace WPPack\Component\Scim\Mapping;

final readonly class ScimAttributeMapping
{
    public function __construct(
        public string $scimPath,
        public string $metaKey,
    ) {}
}
