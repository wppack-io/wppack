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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;

#[CoversClass(AwsProviderSettings::class)]
final class AwsProviderSettingsTest extends TestCase
{
    #[Test]
    public function defaultValuesAreEmpty(): void
    {
        $settings = new AwsProviderSettings();

        self::assertSame('', $settings->region);
        self::assertSame('', $settings->accessKeyId);
        self::assertSame('', $settings->secretAccessKey);
    }

    #[Test]
    public function sensitiveFieldsExposesSecretFields(): void
    {
        self::assertSame(['accessKeyId', 'secretAccessKey'], AwsProviderSettings::sensitiveFields());
    }

    #[Test]
    public function toArrayContainsAllFields(): void
    {
        $settings = new AwsProviderSettings(
            region: 'us-east-1',
            accessKeyId: 'AKIA...',
            secretAccessKey: 'secret-value',
        );

        self::assertSame([
            'region' => 'us-east-1',
            'accessKeyId' => 'AKIA...',
            'secretAccessKey' => 'secret-value',
        ], $settings->toArray());
    }

    #[Test]
    public function fromArrayIsReversibleWithToArray(): void
    {
        $source = [
            'region' => 'ap-northeast-1',
            'accessKeyId' => 'AKIA2',
            'secretAccessKey' => 'shhh',
        ];

        $settings = AwsProviderSettings::fromArray($source);

        self::assertSame($source, $settings->toArray());
    }

    #[Test]
    public function fromArrayToleratesMissingFields(): void
    {
        $settings = AwsProviderSettings::fromArray([]);

        self::assertSame('', $settings->region);
        self::assertSame('', $settings->accessKeyId);
        self::assertSame('', $settings->secretAccessKey);
    }
}
