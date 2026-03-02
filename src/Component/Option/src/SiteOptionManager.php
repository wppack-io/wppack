<?php

declare(strict_types=1);

namespace WpPack\Component\Option;

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
