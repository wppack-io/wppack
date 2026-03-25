<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Nonce;

final class NonceManager
{
    public function create(string $action): string
    {
        return wp_create_nonce($action);
    }

    public function verify(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    public function field(string $action, string $name = '_wpnonce'): string
    {
        return wp_nonce_field($action, $name, true, false);
    }

    public function url(string $url, string $action): string
    {
        return wp_nonce_url($url, $action);
    }

    public function tick(): int
    {
        return (int) wp_nonce_tick();
    }
}
