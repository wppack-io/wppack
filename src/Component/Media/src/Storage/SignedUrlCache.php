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

namespace WPPack\Component\Media\Storage;

use WPPack\Component\Transient\TransientManager;

final readonly class SignedUrlCache
{
    private const TRANSIENT_PREFIX = 'wppack_signed_url:';

    public function __construct(
        private TransientManager $transient,
    ) {}

    public function get(string $key): ?string
    {
        $value = $this->transient->get(self::TRANSIENT_PREFIX . md5($key));

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function set(string $key, string $url, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        $this->transient->set(self::TRANSIENT_PREFIX . md5($key), $url, $ttl);
    }
}
