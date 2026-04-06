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

namespace WpPack\Plugin\PasskeyLoginPlugin\Configuration;

final readonly class PasskeyLoginConfiguration
{
    public const OPTION_NAME = 'wppack_passkey_login';

    /**
     * Map of constructor parameter names to environment variable names.
     *
     * @var array<string, string>
     */
    public const ENV_MAP = [
        'enabled' => 'PASSKEY_ENABLED',
        'rpName' => 'PASSKEY_RP_NAME',
        'rpId' => 'PASSKEY_RP_ID',
        'allowSignup' => 'PASSKEY_ALLOW_SIGNUP',
        'requireUserVerification' => 'PASSKEY_REQUIRE_USER_VERIFICATION',
        'algorithms' => 'PASSKEY_ALGORITHMS',
        'attestation' => 'PASSKEY_ATTESTATION',
        'authenticatorAttachment' => 'PASSKEY_AUTHENTICATOR_ATTACHMENT',
        'timeout' => 'PASSKEY_TIMEOUT',
        'residentKey' => 'PASSKEY_RESIDENT_KEY',
    ];

    /**
     * @param list<int> $algorithms COSE algorithm identifiers
     */
    public function __construct(
        public bool $enabled = true,
        public string $rpName = '',
        public string $rpId = '',
        public bool $allowSignup = false,
        public string $requireUserVerification = 'preferred',
        public array $algorithms = [-7, -257],
        public string $attestation = 'none',
        public string $authenticatorAttachment = '',
        public int $timeout = 60000,
        public string $residentKey = 'required',
    ) {}

    /**
     * Create from environment variables/constants with wp_options fallback.
     *
     * Priority: constant > wp_options > env > default
     */
    public static function fromEnvironmentOrOptions(): self
    {
        $raw = get_option(self::OPTION_NAME, []);
        $options = \is_array($raw) ? $raw : [];

        return new self(
            enabled: self::resolveBool('PASSKEY_ENABLED', $options, true),
            rpName: self::resolveString('PASSKEY_RP_NAME', $options, ''),
            rpId: self::resolveString('PASSKEY_RP_ID', $options, ''),
            allowSignup: self::resolveBool('PASSKEY_ALLOW_SIGNUP', $options, false),
            requireUserVerification: self::resolveString('PASSKEY_REQUIRE_USER_VERIFICATION', $options, 'preferred'),
            algorithms: self::resolveAlgorithms('PASSKEY_ALGORITHMS', $options, [-7, -257]),
            attestation: self::resolveString('PASSKEY_ATTESTATION', $options, 'none'),
            authenticatorAttachment: self::resolveString('PASSKEY_AUTHENTICATOR_ATTACHMENT', $options, ''),
            timeout: self::resolveInt('PASSKEY_TIMEOUT', $options, 60000),
            residentKey: self::resolveString('PASSKEY_RESIDENT_KEY', $options, 'required'),
        );
    }

    /**
     * Resolve string: constant > option > env > default.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveString(string $envName, array $options, string $default): string
    {
        if (\defined($envName)) {
            $v = \constant($envName);

            return \is_string($v) && $v !== '' ? $v : $default;
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName]) && \is_string($options[$paramName]) && $options[$paramName] !== '') {
            return $options[$paramName];
        }

        return self::getEnv($envName) ?? $default;
    }

    /**
     * Resolve bool: constant > option > env > default.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveBool(string $envName, array $options, bool $default): bool
    {
        if (\defined($envName)) {
            return (bool) \constant($envName);
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName])) {
            return (bool) $options[$paramName];
        }

        return self::getBool($envName, $default);
    }

    /**
     * Resolve int: constant > option > env > default.
     *
     * @param array<string, mixed> $options
     */
    private static function resolveInt(string $envName, array $options, int $default): int
    {
        if (\defined($envName)) {
            return (int) \constant($envName);
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName]) && is_numeric($options[$paramName])) {
            return (int) $options[$paramName];
        }

        $env = self::getEnv($envName);

        return $env !== null && is_numeric($env) ? (int) $env : $default;
    }

    /**
     * Resolve algorithms: constant > option > env > default.
     *
     * Constant/env value is a comma-separated string of COSE IDs (e.g. "-7,-257,-8").
     *
     * @param array<string, mixed> $options
     * @param list<int>            $default
     *
     * @return list<int>
     */
    private static function resolveAlgorithms(string $envName, array $options, array $default): array
    {
        if (\defined($envName)) {
            $v = \constant($envName);

            return \is_string($v) ? self::parseAlgorithms($v) : $default;
        }

        $paramName = self::envToParam($envName);
        if (isset($options[$paramName]) && \is_array($options[$paramName])) {
            return array_values(array_map('intval', $options[$paramName]));
        }

        $env = self::getEnv($envName);

        return $env !== null ? self::parseAlgorithms($env) : $default;
    }

    /**
     * Parse comma-separated COSE algorithm IDs.
     *
     * @return list<int>
     */
    private static function parseAlgorithms(string $value): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn(string $s): bool => $s !== '');

        return array_values(array_map('intval', $parts));
    }

    private static function getEnv(string $name): ?string
    {
        if (\defined($name)) {
            $value = \constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }

    private static function getBool(string $name, bool $default): bool
    {
        if (\defined($name)) {
            return (bool) \constant($name);
        }

        $value = $_ENV[$name] ?? false;

        if ($value === false) {
            $value = getenv($name);
        }

        if ($value === false) {
            return $default;
        }

        return \in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Convert environment variable name to parameter name.
     * e.g., PASSKEY_RP_NAME -> rpName
     */
    private static function envToParam(string $envName): string
    {
        static $flipped = null;
        $flipped ??= array_flip(self::ENV_MAP);

        return $flipped[$envName] ?? $envName;
    }
}
