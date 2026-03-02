<?php

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
