<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Condition;

interface ConditionInterface
{
    public function isSatisfied(): bool;
}
