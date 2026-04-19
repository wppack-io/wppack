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

namespace WPPack\Component\DependencyInjection\Tests\Attribute\Fixtures;

use WPPack\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: 'alias.one')]
#[AsAlias(id: 'alias.two')]
final class MultiAliasService
{
    public function getValue(): string
    {
        return 'multi';
    }
}
