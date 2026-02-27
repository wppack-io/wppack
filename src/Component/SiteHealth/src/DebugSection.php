<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth;

abstract class DebugSection
{
    /**
     * @return array<string, array{label: string, value: string|int|float|array<mixed>, debug?: string, private?: bool}>
     */
    abstract public function getFields(): array;
}
