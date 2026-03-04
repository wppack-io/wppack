<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

final class ValidationContext
{
    /** @var array<string, mixed> */
    private readonly array $oldValues;

    public function __construct(
        private readonly string $optionGroup,
        string $optionName,
    ) {
        $values = get_option($optionName, []);
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
