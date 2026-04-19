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

namespace WPPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithArrayParam
{
    public function __construct(
        public string $name,
        /** @var list<string> */
        public array $tags = [],
    ) {}
}
