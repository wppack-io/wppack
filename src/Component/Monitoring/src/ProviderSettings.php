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

namespace WPPack\Component\Monitoring;

/**
 * Base provider settings. Bridge-specific subclasses add their own fields.
 */
readonly class ProviderSettings
{
    /**
     * @return list<string> Field names that contain sensitive values (for masking)
     */
    public static function sensitiveFields(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self();
    }
}
