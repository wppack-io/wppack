<?php

declare(strict_types=1);

namespace WpPack\Component\Transient;

final class SiteTransientManager
{
    public function get(string $transient): mixed
    {
        return get_site_transient($transient);
    }

    public function set(string $transient, mixed $value, int $expiration = 0): bool
    {
        return set_site_transient($transient, $value, $expiration);
    }

    public function delete(string $transient): bool
    {
        return delete_site_transient($transient);
    }
}
