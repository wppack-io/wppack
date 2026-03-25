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

interface DataCollectorInterface
{
    public function getName(): string;

    public function collect(): void;

    /** @return array<string, mixed> */
    public function getData(): array;

    public function getLabel(): string;

    public function getIndicatorValue(): string;

    public function getIndicatorColor(): string;

    public function reset(): void;
}
