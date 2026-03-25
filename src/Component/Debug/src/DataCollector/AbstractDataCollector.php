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

namespace WpPack\Component\Debug\DataCollector;

abstract class AbstractDataCollector implements DataCollectorInterface
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function getLabel(): string
    {
        return ucfirst($this->getName());
    }

    public function getIndicatorValue(): string
    {
        return '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
