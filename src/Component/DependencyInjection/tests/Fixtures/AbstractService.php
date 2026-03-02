<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

abstract class AbstractService
{
    abstract public function execute(): void;
}
