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

    public function getIndicatorValue(): string
    {
        return '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void {}
}
