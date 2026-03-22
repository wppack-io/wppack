<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage;

use WpPack\Component\Transient\TransientManager;

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
        $this->transient->set(self::TRANSIENT_PREFIX . md5($key), $url, $ttl);
    }
}
