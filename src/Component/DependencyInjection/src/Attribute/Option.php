<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Option extends Autowire
{
    public function __construct(string $name)
    {
        parent::__construct(option: $name);
    }
}
