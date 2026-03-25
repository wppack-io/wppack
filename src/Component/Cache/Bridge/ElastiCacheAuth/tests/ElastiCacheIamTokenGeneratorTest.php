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

namespace WpPack\Component\Cache\Bridge\ElastiCacheAuth\Tests;

use AsyncAws\Core\Credentials\Credentials;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\ElastiCacheAuth\ElastiCacheIamTokenGenerator;

final class ElastiCacheIamTokenGeneratorTest extends TestCase
{
    #[Test]
    public function generateTokenReturnsSignedUrl(): void
    {
        $credentials = new Credentials('AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
        $generator = new ElastiCacheIamTokenGenerator(
            region: 'ap-northeast-1',
            userId: 'my-iam-user',
            credentialProvider: $credentials,
        );

        $host = 'my-cluster.xxxxx.apne1.cache.amazonaws.com:6379';
        $token = $generator->generateToken($host);

        // Token must not contain http:// prefix
        self::assertStringNotContainsString('http://', $token);

        // Token must contain the host
        self::assertStringContainsString('my-cluster.xxxxx.apne1.cache.amazonaws.com', $token);

        // Token must contain the connect action
        self::assertStringContainsString('Action=connect', $token);

        // Token must contain the user
        self::assertStringContainsString('User=my-iam-user', $token);

        // Token must contain SigV4 signature
        self::assertStringContainsString('X-Amz-Signature', $token);

        // Token must have 900 second expiry (15 minutes)
        self::assertStringContainsString('X-Amz-Expires=900', $token);
    }

    #[Test]
    public function createProviderReturnsClosure(): void
    {
        $credentials = new Credentials('AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
        $generator = new ElastiCacheIamTokenGenerator(
            region: 'ap-northeast-1',
            userId: 'my-iam-user',
            credentialProvider: $credentials,
        );

        $host = 'my-cluster.xxxxx.apne1.cache.amazonaws.com:6379';
        $provider = $generator->createProvider($host);

        self::assertInstanceOf(\Closure::class, $provider);

        $token = $provider();
        self::assertIsString($token);
        self::assertStringContainsString('X-Amz-Signature', $token);
    }

    #[Test]
    public function throwsWhenCredentialsCannotBeResolved(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve AWS credentials');

        // Expired credentials will return null from getCredentials()
        $expiredCredentials = new Credentials(
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            null,
            new \DateTimeImmutable('-1 hour'),
        );

        $generator = new ElastiCacheIamTokenGenerator(
            region: 'ap-northeast-1',
            userId: 'my-iam-user',
            credentialProvider: $expiredCredentials,
        );

        $generator->generateToken('my-cluster.xxxxx.apne1.cache.amazonaws.com:6379');
    }
}
