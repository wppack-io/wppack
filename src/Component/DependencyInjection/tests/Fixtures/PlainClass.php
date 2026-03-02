<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class PlainClass
{
    public function doSomething(): void {}
}
