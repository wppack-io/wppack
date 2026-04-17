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

namespace WpPack\Component\Database\Tests\Driver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Exception\CredentialsExpiredException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Exception\DriverThrottledException;
use WpPack\Component\Database\Exception\DriverTimeoutException;

/**
 * Unit tests for the exception-classification helper in
 * DataApiDriverTrait::classifyDataApiError(). Drives the private method
 * through reflection on an anonymous double so we don't need the
 * async-aws SDK installed (which would otherwise be required just to
 * instantiate a real driver).
 */
final class DataApiErrorClassificationTest extends TestCase
{
    /**
     * Thin double that exposes just enough of DataApiDriverTrait surface
     * to invoke classifyDataApiError. Real driver is not instantiated
     * because its dependencies require async-aws/rds-data-service which
     * isn't in the dev dep set.
     */
    private static function invokeClassify(string $sql, \Throwable $e): DriverException
    {
        $harness = new class {
            use \WpPack\Component\Database\Driver\DataApiDriverTrait;

            public function callClassify(string $sql, \Throwable $e): DriverException
            {
                return $this->classifyDataApiError($sql, $e);
            }
        };

        return $harness->callClassify($sql, $e);
    }

    #[Test]
    public function throttlingExceptionClassMapsToDriverThrottledException(): void
    {
        // classifyDataApiError matches 'Throttl' in either the class name
        // or the message; here we exercise the message-content path via a
        // plain RuntimeException carrying a Throttling-style error.
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('Rate exceeded'));

        self::assertInstanceOf(DriverThrottledException::class, $ex);
    }

    #[Test]
    public function timeoutMessageMapsToDriverTimeoutException(): void
    {
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('Request timed out after 30s'));

        self::assertInstanceOf(DriverTimeoutException::class, $ex);
    }

    #[Test]
    public function expiredTokenMessageMapsToCredentialsExpired(): void
    {
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('ExpiredTokenException: session expired'));

        self::assertInstanceOf(CredentialsExpiredException::class, $ex);
    }

    #[Test]
    public function signatureExpiredMapsToCredentialsExpired(): void
    {
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('signature has expired'));

        self::assertInstanceOf(CredentialsExpiredException::class, $ex);
    }

    #[Test]
    public function otherErrorsFallThroughToDriverException(): void
    {
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('syntax error at or near "FOO"'));

        self::assertInstanceOf(DriverException::class, $ex);
        self::assertNotInstanceOf(DriverThrottledException::class, $ex);
        self::assertNotInstanceOf(DriverTimeoutException::class, $ex);
        self::assertNotInstanceOf(CredentialsExpiredException::class, $ex);
    }

    #[Test]
    public function messageContains429MapsToThrottled(): void
    {
        $ex = self::invokeClassify('SELECT 1', new \RuntimeException('HTTP 429 Too Many Requests'));

        self::assertInstanceOf(DriverThrottledException::class, $ex);
    }
}
