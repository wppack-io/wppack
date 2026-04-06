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

namespace WpPack\Component\Security\Bridge\Passkey\Badge;

use WpPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;

final class PasskeyCredentialBadge implements BadgeInterface
{
    public function __construct(
        public readonly string $credentialId,
        public readonly int $newCounter,
        public readonly bool $backupEligible,
    ) {}

    public function isResolved(): bool
    {
        return true;
    }
}
