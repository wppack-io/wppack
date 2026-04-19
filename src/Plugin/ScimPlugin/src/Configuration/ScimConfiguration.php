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

namespace WPPack\Plugin\ScimPlugin\Configuration;

final readonly class ScimConfiguration
{
    public const OPTION_NAME = 'wppack_scim';

    public const MASKED_VALUE = '********';

    public function __construct(
        #[\SensitiveParameter]
        public string $bearerToken,
        public bool $autoProvision = true,
        public string $defaultRole = 'subscriber',
        public bool $allowGroupManagement = true,
        public bool $allowUserDeletion = false,
        public int $maxResults = 100,
    ) {}

    public static function fromEnvironment(): self
    {
        $token = \defined('SCIM_BEARER_TOKEN')
            ? \constant('SCIM_BEARER_TOKEN')
            : getenv('SCIM_BEARER_TOKEN');

        if ($token === false || $token === '') {
            throw new \RuntimeException('SCIM_BEARER_TOKEN is not configured.');
        }

        return new self(
            bearerToken: $token,
            autoProvision: self::envBool('SCIM_AUTO_PROVISION', true),
            defaultRole: self::envString('SCIM_DEFAULT_ROLE', 'subscriber'),
            allowGroupManagement: self::envBool('SCIM_ALLOW_GROUP_MANAGEMENT', true),
            allowUserDeletion: self::envBool('SCIM_ALLOW_USER_DELETION', false),
            maxResults: self::envInt('SCIM_MAX_RESULTS', 100),
        );
    }

    /**
     * Load from constants → wp_options → defaults.
     */
    public static function fromEnvironmentOrOptions(): self
    {
        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $token = self::resolveString('SCIM_BEARER_TOKEN', $saved['bearerToken'] ?? '', '');
        if ($token === '') {
            throw new \RuntimeException('SCIM_BEARER_TOKEN is not configured.');
        }

        return new self(
            bearerToken: $token,
            autoProvision: self::resolveBool('SCIM_AUTO_PROVISION', $saved['autoProvision'] ?? null, true),
            defaultRole: self::resolveString('SCIM_DEFAULT_ROLE', $saved['defaultRole'] ?? null, 'subscriber'),
            allowGroupManagement: self::resolveBool('SCIM_ALLOW_GROUP_MANAGEMENT', $saved['allowGroupManagement'] ?? null, true),
            allowUserDeletion: self::resolveBool('SCIM_ALLOW_USER_DELETION', $saved['allowUserDeletion'] ?? null, false),
            maxResults: self::resolveInt('SCIM_MAX_RESULTS', $saved['maxResults'] ?? null, 100),
        );
    }

    /**
     * Check if a valid token is configured (from any source).
     */
    public static function hasToken(): bool
    {
        if (\defined('SCIM_BEARER_TOKEN') && \constant('SCIM_BEARER_TOKEN') !== '') {
            return true;
        }

        $env = getenv('SCIM_BEARER_TOKEN');
        if ($env !== false && $env !== '') {
            return true;
        }

        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        return isset($saved['bearerToken']) && $saved['bearerToken'] !== '';
    }

    private static function resolveString(string $constName, mixed $optionValue, string $default): string
    {
        if (\defined($constName)) {
            return (string) \constant($constName);
        }

        $env = getenv($constName);
        if ($env !== false && $env !== '') {
            return $env;
        }

        if (\is_string($optionValue) && $optionValue !== '') {
            return $optionValue;
        }

        return $default;
    }

    private static function resolveBool(string $constName, mixed $optionValue, bool $default): bool
    {
        if (\defined($constName)) {
            return filter_var(\constant($constName), \FILTER_VALIDATE_BOOLEAN);
        }

        $env = getenv($constName);
        if ($env !== false) {
            return filter_var($env, \FILTER_VALIDATE_BOOLEAN);
        }

        if ($optionValue !== null) {
            return filter_var($optionValue, \FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }

    private static function resolveInt(string $constName, mixed $optionValue, int $default): int
    {
        if (\defined($constName)) {
            return max(1, min(1000, (int) \constant($constName)));
        }

        $env = getenv($constName);
        if ($env !== false) {
            return max(1, min(1000, (int) $env));
        }

        if ($optionValue !== null) {
            return max(1, min(1000, (int) $optionValue));
        }

        return $default;
    }

    private static function envString(string $name, string $default): string
    {
        if (\defined($name)) {
            return (string) \constant($name);
        }

        $value = getenv($name);

        return $value !== false ? $value : $default;
    }

    private static function envInt(string $name, int $default): int
    {
        $value = self::envString($name, (string) $default);

        return max(1, min(1000, (int) $value));
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = self::envString($name, $default ? 'true' : 'false');

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }
}
