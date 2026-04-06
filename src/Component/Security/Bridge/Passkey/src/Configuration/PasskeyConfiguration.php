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

namespace WpPack\Component\Security\Bridge\Passkey\Configuration;

final readonly class PasskeyConfiguration
{
    public function __construct(
        public string $rpName = '',
        public string $rpId = '',
        public string $origin = '',
        public int $timeout = 60000,
        public string $attestation = 'none',
        public string $userVerification = 'preferred',
        public bool $requireResidentKey = true,
    ) {}
}
