<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;

#[AsDataCollector(name: 'low', priority: -10)]
final class LowPriorityCollector implements DataCollectorInterface
{
    public function getName(): string
    {
        return 'low';
    }

    public function collect(): void {}

    public function getData(): array
    {
        return [];
    }

    public function getLabel(): string
    {
        return 'Low';
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
