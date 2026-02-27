<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth;

interface DebugSectionInterface
{
    /**
     * @return array<string, array{label: string, value: string|int|float|array<mixed>, debug?: string, private?: bool}>
     */
    public function getFields(): array;
}
