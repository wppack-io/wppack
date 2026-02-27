<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Exception;

final class ParameterNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Parameter "%s" not found.', $name));
    }
}
