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

namespace WPPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Rest\HttpMethod;

final class HttpMethodTest extends TestCase
{
    #[Test]
    public function allCasesHaveCorrectValues(): void
    {
        self::assertSame('GET', HttpMethod::GET->value);
        self::assertSame('POST', HttpMethod::POST->value);
        self::assertSame('PUT', HttpMethod::PUT->value);
        self::assertSame('PATCH', HttpMethod::PATCH->value);
        self::assertSame('DELETE', HttpMethod::DELETE->value);
    }

    #[Test]
    public function backingTypeIsString(): void
    {
        $reflection = new \ReflectionEnum(HttpMethod::class);

        self::assertSame('string', $reflection->getBackingType()?->getName());
    }
}
