<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Option;

final class OptionManager
{
    public function get(string $option, mixed $default = false): mixed
    {
        return get_option($option, $default);
    }

    public function add(string $option, mixed $value = '', ?bool $autoload = null): bool
    {
        return add_option($option, $value, '', $autoload);
    }

    public function update(string $option, mixed $value, ?bool $autoload = null): bool
    {
        return update_option($option, $value, $autoload);
    }

    public function delete(string $option): bool
    {
        return delete_option($option);
    }
}
