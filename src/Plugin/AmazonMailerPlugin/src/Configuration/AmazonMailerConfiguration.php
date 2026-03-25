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

namespace WpPack\Plugin\AmazonMailerPlugin\Configuration;

final readonly class AmazonMailerConfiguration
{
    public function __construct(
        public string $dsn,
    ) {}

    public static function fromEnvironment(): self
    {
        if (\defined('MAILER_DSN')) {
            return new self(dsn: (string) \constant('MAILER_DSN'));
        }

        $dsn = self::getEnv('MAILER_DSN');

        if ($dsn === null) {
            throw new \RuntimeException('MAILER_DSN is not configured. Define it as a PHP constant in wp-config.php or as an environment variable.');
        }

        return new self(dsn: $dsn);
    }

    private static function getEnv(string $name): ?string
    {
        $value = $_ENV[$name] ?? false;

        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
