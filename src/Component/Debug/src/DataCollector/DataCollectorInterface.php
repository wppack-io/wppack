<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

interface DataCollectorInterface
{
    public function getName(): string;

    public function collect(): void;

    /** @return array<string, mixed> */
    public function getData(): array;

    public function getLabel(): string;

    public function getBadgeValue(): string;

    public function getBadgeColor(): string;

    public function reset(): void;
}
