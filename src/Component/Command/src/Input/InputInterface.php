<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Input;

interface InputInterface
{
    /** @return string|int|float|bool|list<string>|null */
    public function getArgument(string $name): string|int|float|bool|array|null;

    public function getOption(string $name): string|int|float|bool|null;

    public function hasOption(string $name): bool;
}
