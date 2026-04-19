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

final class SiteOptionManager
{
    public function get(string $option, mixed $default = false): mixed
    {
        return get_site_option($option, $default);
    }

    public function update(string $option, mixed $value): bool
    {
        return update_site_option($option, $value);
    }

    public function delete(string $option): bool
    {
        return delete_site_option($option);
    }
}
