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

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\RequestValueResolver;

final class RequestValueResolverTest extends TestCase
{
    private RequestValueResolver $resolver;
    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request(query: ['tab' => 'general']);
        $this->resolver = new RequestValueResolver($this->request);
    }

    #[Test]
    public function supportsRequestParameter(): void
    {
        $param = new \ReflectionParameter(
            static fn(Request $request) => null,
            'request',
        );

        self::assertTrue($this->resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportNonRequestParameter(): void
    {
        $param = new \ReflectionParameter(
            static fn(string $name) => null,
            'name',
        );

        self::assertFalse($this->resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportUntypedParameter(): void
    {
        $param = new \ReflectionParameter(
            static fn($value) => null,
            'value',
        );

        self::assertFalse($this->resolver->supports($param));
    }

    #[Test]
    public function resolvesRequestInstance(): void
    {
        $param = new \ReflectionParameter(
            static fn(Request $request) => null,
            'request',
        );

        self::assertSame($this->request, $this->resolver->resolve($param));
    }
}
