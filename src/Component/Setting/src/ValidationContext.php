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

namespace WPPack\Component\Setting;

use WPPack\Component\Option\OptionManager;

final class ValidationContext
{
    /** @var array<string, mixed> */
    private readonly array $oldValues;

    public function __construct(
        private readonly string $optionGroup,
        string $optionName,
        OptionManager $optionManager,
    ) {
        $values = $optionManager->get($optionName, []);
        $this->oldValues = \is_array($values) ? $values : [];
    }

    public function error(string $code, string $message): void
    {
        add_settings_error($this->optionGroup, $code, $message, 'error');
    }

    public function warning(string $code, string $message): void
    {
        add_settings_error($this->optionGroup, $code, $message, 'warning');
    }

    public function info(string $code, string $message): void
    {
        add_settings_error($this->optionGroup, $code, $message, 'info');
    }

    public function oldValue(string $key, mixed $default = null): mixed
    {
        return $this->oldValues[$key] ?? $default;
    }
}
