<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;

#[AsDataCollector(name: 'default')]
final class DefaultPriorityCollector implements DataCollectorInterface
{
    public function getName(): string
    {
        return 'default';
    }

    public function collect(): void {}

    public function getData(): array
    {
        return [];
    }

    public function getLabel(): string
    {
        return 'Default';
    }

    public function getBadgeValue(): string
    {
        return '';
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }

    public function reset(): void {}
}
