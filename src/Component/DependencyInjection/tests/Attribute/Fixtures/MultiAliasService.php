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

namespace WpPack\Component\DependencyInjection\Tests\Attribute\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: 'alias.one')]
#[AsAlias(id: 'alias.two')]
final class MultiAliasService
{
    public function getValue(): string
    {
        return 'multi';
    }
}
