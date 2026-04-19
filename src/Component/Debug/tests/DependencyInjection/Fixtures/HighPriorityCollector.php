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

namespace WPPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WPPack\Component\Debug\Attribute\AsDataCollector;
use WPPack\Component\Debug\DataCollector\DataCollectorInterface;

#[AsDataCollector(name: 'high', priority: 100)]
final class HighPriorityCollector implements DataCollectorInterface
{
    public function getName(): string
    {
        return 'high';
    }

    public function collect(): void {}

    public function getData(): array
    {
        return [];
    }

    public function getLabel(): string
    {
        return 'High';
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
