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

namespace WPPack\Component\Monitoring\Bridge\Cloudflare\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\Cloudflare\CloudflareProviderSettings;

#[CoversClass(CloudflareProviderSettings::class)]
final class CloudflareProviderSettingsTest extends TestCase
{
    #[Test]
    public function defaultsAreEmpty(): void
    {
        $settings = new CloudflareProviderSettings();

        self::assertSame('', $settings->apiToken);
        self::assertSame('', $settings->hostname);
    }

    #[Test]
    public function apiTokenMarkedSensitiveHostnameIsNot(): void
    {
        self::assertSame(['apiToken'], CloudflareProviderSettings::sensitiveFields());
    }

    #[Test]
    public function toArrayRoundTripPreservesFields(): void
    {
        $source = ['apiToken' => 'tok_abc', 'hostname' => 'example.com'];

        $settings = CloudflareProviderSettings::fromArray($source);

        self::assertSame('tok_abc', $settings->apiToken);
        self::assertSame('example.com', $settings->hostname);
        self::assertSame($source, $settings->toArray());
    }

    #[Test]
    public function fromArrayTolerantToMissingKeys(): void
    {
        $settings = CloudflareProviderSettings::fromArray([]);

        self::assertSame('', $settings->apiToken);
        self::assertSame('', $settings->hostname);
    }
}
