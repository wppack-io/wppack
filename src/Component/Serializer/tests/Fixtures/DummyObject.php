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

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class DummyObject
{
    public function __construct(
        public string $name,
        public int $value,
    ) {}
}
