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

namespace WpPack\Component\Security\Bridge\Passkey\Storage;

/**
 * @param list<string> $transports
 */
final readonly class PasskeyCredential
{
    /**
     * @param list<string> $transports
     */
    public function __construct(
        public int $id,
        public int $userId,
        public string $credentialId,
        public string $publicKey,
        public int $counter,
        public array $transports,
        public string $deviceName,
        public string $aaguid,
        public bool $backupEligible,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastUsedAt,
    ) {}
}
