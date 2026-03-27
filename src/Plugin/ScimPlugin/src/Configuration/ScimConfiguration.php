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

namespace WpPack\Plugin\ScimPlugin\Configuration;

final readonly class ScimConfiguration
{
    public function __construct(
        #[\SensitiveParameter]
        public string $bearerToken,
        public int $serviceAccountUserId = 1,
        public bool $autoProvision = true,
        public string $defaultRole = 'subscriber',
        public bool $allowGroupManagement = true,
        public bool $allowUserDeletion = false,
        public ?int $blogId = null,
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
            serviceAccountUserId: self::envInt('SCIM_SERVICE_ACCOUNT_USER_ID', 1),
            autoProvision: self::envBool('SCIM_AUTO_PROVISION', true),
            defaultRole: self::envString('SCIM_DEFAULT_ROLE', 'subscriber'),
            allowGroupManagement: self::envBool('SCIM_ALLOW_GROUP_MANAGEMENT', true),
            allowUserDeletion: self::envBool('SCIM_ALLOW_USER_DELETION', false),
            blogId: self::envIntOrNull('SCIM_BLOG_ID'),
            maxResults: self::envInt('SCIM_MAX_RESULTS', 100),
        );
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

        return (int) $value;
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = self::envString($name, $default ? 'true' : 'false');

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }

    private static function envIntOrNull(string $name): ?int
    {
        if (\defined($name)) {
            return (int) \constant($name);
        }

        $value = getenv($name);

        return $value !== false ? (int) $value : null;
    }
}
