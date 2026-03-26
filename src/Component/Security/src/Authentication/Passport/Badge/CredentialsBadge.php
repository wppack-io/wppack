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

namespace WpPack\Component\Security\Authentication\Passport\Badge;

final class CredentialsBadge implements BadgeInterface
{
    private bool $resolved = false;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $password,
    ) {
        if ($password === '') {
            throw new \InvalidArgumentException('Password must not be empty.');
        }

        if (\strlen($password) > 4096) {
            throw new \InvalidArgumentException('Password must not exceed 4096 characters.');
        }

        if (str_contains($password, "\0")) {
            throw new \InvalidArgumentException('Password must not contain null bytes.');
        }
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function markResolved(): void
    {
        $this->resolved = true;
    }
}
