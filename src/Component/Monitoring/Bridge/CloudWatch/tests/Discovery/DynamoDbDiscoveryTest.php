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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\AwsProviderSettings;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DynamoDbDiscovery;

#[CoversClass(DynamoDbDiscovery::class)]
final class DynamoDbDiscoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['CACHE_DSN']);
    }

    #[Test]
    public function emptyEnvProducesNoProviders(): void
    {
        unset($_ENV['CACHE_DSN']);

        self::assertSame([], (new DynamoDbDiscovery())->getProviders());
    }

    #[Test]
    public function nonDynamoDbSchemeSkipped(): void
    {
        $_ENV['CACHE_DSN'] = 'redis://127.0.0.1:6379/0';

        self::assertSame([], (new DynamoDbDiscovery())->getProviders());
    }

    #[Test]
    public function invalidDsnSkipped(): void
    {
        $_ENV['CACHE_DSN'] = 'not-a-valid-dsn-at-all';

        self::assertSame([], (new DynamoDbDiscovery())->getProviders());
    }

    #[Test]
    public function hostThatIsNotAnAwsRegionSkipped(): void
    {
        // host must match ^[a-z]{2,4}-[a-z]+-\d+$ — 'localhost' obviously doesn't.
        $_ENV['CACHE_DSN'] = 'dynamodb://localhost/mytable';

        self::assertSame([], (new DynamoDbDiscovery())->getProviders());
    }

    #[Test]
    public function discoveredProviderCarriesRegionAndTable(): void
    {
        $_ENV['CACHE_DSN'] = 'dynamodb://us-east-1/wp_object_cache';

        $providers = (new DynamoDbDiscovery())->getProviders();

        self::assertCount(1, $providers);
        $provider = $providers[0];
        self::assertSame('dynamodb', $provider->id);
        self::assertSame('DynamoDB (wp_object_cache)', $provider->label);
        self::assertSame('cloudwatch', $provider->bridge);
        self::assertTrue($provider->locked);
        self::assertInstanceOf(AwsProviderSettings::class, $provider->settings);
        self::assertSame('us-east-1', $provider->settings->region);

        self::assertCount(6, $provider->metrics);
        self::assertSame(['TableName' => 'wp_object_cache'], $provider->metrics[0]->dimensions);
        foreach ($provider->metrics as $metric) {
            self::assertTrue($metric->locked);
            self::assertSame('AWS/DynamoDB', $metric->namespace);
        }
    }

    #[Test]
    public function missingPathDefaultsToCacheTable(): void
    {
        $_ENV['CACHE_DSN'] = 'dynamodb://us-west-2';

        $providers = (new DynamoDbDiscovery())->getProviders();

        self::assertCount(1, $providers);
        self::assertSame('DynamoDB (cache)', $providers[0]->label);
        self::assertSame(['TableName' => 'cache'], $providers[0]->metrics[0]->dimensions);
    }

    #[Test]
    public function emptyPathDefaultsToCacheTable(): void
    {
        $_ENV['CACHE_DSN'] = 'dynamodb://ap-northeast-1/';

        $providers = (new DynamoDbDiscovery())->getProviders();

        self::assertSame(['TableName' => 'cache'], $providers[0]->metrics[0]->dimensions);
    }
}
