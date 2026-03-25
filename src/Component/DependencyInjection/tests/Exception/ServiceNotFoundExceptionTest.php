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

namespace WpPack\Component\DependencyInjection\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use WpPack\Component\DependencyInjection\Exception\ServiceNotFoundException;

final class ServiceNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsServiceId(): void
    {
        $exception = new ServiceNotFoundException('foo');

        self::assertSame('Service "foo" not found.', $exception->getMessage());
    }

    #[Test]
    public function implementsNotFoundExceptionInterface(): void
    {
        $exception = new ServiceNotFoundException('foo');

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
    }

    #[Test]
    public function extendsInvalidArgumentException(): void
    {
        $exception = new ServiceNotFoundException('foo');

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
