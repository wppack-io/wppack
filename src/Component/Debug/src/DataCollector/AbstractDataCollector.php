<?php

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

    public function getBadgeValue(): string
    {
        return '';
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
