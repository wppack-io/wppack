<?php

declare(strict_types=1);

namespace WpPack\Component\Transient;

final class TransientManager
{
    public function get(string $transient): mixed
    {
        return get_transient($transient);
    }

    public function set(string $transient, mixed $value, int $expiration = 0): bool
    {
        return set_transient($transient, $value, $expiration);
    }

    public function delete(string $transient): bool
    {
        return delete_transient($transient);
    }
}
