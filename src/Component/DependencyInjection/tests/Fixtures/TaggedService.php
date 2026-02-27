<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService(tags: ['app.handler'])]
final class TaggedService
{
    public function handle(): void {}
}
