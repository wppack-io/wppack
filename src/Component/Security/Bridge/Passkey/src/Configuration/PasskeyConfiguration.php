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

namespace WPPack\Component\Security\Bridge\Passkey\Configuration;

final readonly class PasskeyConfiguration
{
    /**
     * @param list<int> $algorithms COSE algorithm identifiers (e.g. [-7, -257])
     */
    public function __construct(
        public string $rpName = '',
        public string $rpId = '',
        public string $origin = '',
        public int $timeout = 60000,
        public string $attestation = 'none',
        public string $userVerification = 'preferred',
        public string $residentKey = 'required',
        public array $algorithms = [-7, -257],
        public string $authenticatorAttachment = '',
        public int $maxCredentialsPerUser = 3,
    ) {}
}
