<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\RequestValueResolver;
use WpPack\Component\HttpFoundation\ValueResolverInterface;

final class ArgumentResolverTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueWhenResolverMatches(): void
    {
        $request = new Request();
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $param = new \ReflectionParameter(
            static fn(Request $request) => null,
            'request',
        );

        self::assertTrue($resolver->supports($param));
    }

    #[Test]
    public function supportsReturnsFalseWhenNoResolverMatches(): void
    {
        $request = new Request();
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $param = new \ReflectionParameter(
            static fn(string $name) => null,
            'name',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function supportsReturnsFalseWithEmptyResolvers(): void
    {
        $resolver = new ArgumentResolver([]);

        $param = new \ReflectionParameter(
            static fn(Request $request) => null,
            'request',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function resolveReturnsValueFromFirstMatchingResolver(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $param = new \ReflectionParameter(
            static fn(Request $request) => null,
            'request',
        );

        self::assertSame($request, $resolver->resolve($param));
    }

    #[Test]
    public function resolveThrowsWhenNoResolverMatches(): void
    {
        $resolver = new ArgumentResolver([]);

        $param = new \ReflectionParameter(
            static fn(string $name) => null,
            'name',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No value resolver supports parameter "$name"');

        $resolver->resolve($param);
    }

    #[Test]
    public function createResolverReturnsNullForMissingMethod(): void
    {
        $resolver = new ArgumentResolver([]);

        self::assertNull($resolver->createResolver(new \stdClass(), 'nonExistent'));
    }

    #[Test]
    public function createResolverReturnsNullForNoArgMethod(): void
    {
        $request = new Request();
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $target = new class {
            public function __invoke(): string
            {
                return '';
            }
        };

        self::assertNull($resolver->createResolver($target));
    }

    #[Test]
    public function createResolverReturnsNullWhenNoParamIsSupported(): void
    {
        $request = new Request();
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $target = new class {
            public function __invoke(string $name): string
            {
                return $name;
            }
        };

        self::assertNull($resolver->createResolver($target));
    }

    #[Test]
    public function createResolverReturnsClosureThatResolvesParams(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $target = new class {
            public function __invoke(Request $request): string
            {
                return $request->query->get('tab', 'default');
            }
        };

        $closure = $resolver->createResolver($target);

        self::assertNotNull($closure);
        $args = $closure();
        self::assertCount(1, $args);
        self::assertSame($request, $args[0]);
    }

    #[Test]
    public function createResolverSkipsUnsupportedParams(): void
    {
        $request = new Request(query: ['tab' => 'general']);
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $target = new class {
            public function __invoke(array $args, Request $request): string
            {
                return '';
            }
        };

        $closure = $resolver->createResolver($target);

        self::assertNotNull($closure);
        $args = $closure();
        // Only the Request param (index 1) is resolved, array $args (index 0) is skipped
        self::assertArrayNotHasKey(0, $args);
        self::assertArrayHasKey(1, $args);
        self::assertSame($request, $args[1]);
    }

    #[Test]
    public function createResolverResolvesLazilyOnEachCall(): void
    {
        $counter = new \stdClass();
        $counter->value = 0;

        $mockResolver = new CountingValueResolver($counter);

        $resolver = new ArgumentResolver([$mockResolver]);

        $target = new class {
            public function __invoke(int $value): int
            {
                return $value;
            }
        };

        $closure = $resolver->createResolver($target);
        self::assertNotNull($closure);

        $args1 = $closure();
        $args2 = $closure();

        self::assertSame(1, $args1[0]);
        self::assertSame(2, $args2[0]);
    }

    #[Test]
    public function createResolverWorksWithCustomMethodName(): void
    {
        $request = new Request();
        $resolver = new ArgumentResolver([new RequestValueResolver($request)]);

        $target = new class {
            public function configure(Request $request): string
            {
                return '';
            }
        };

        $closure = $resolver->createResolver($target, 'configure');

        self::assertNotNull($closure);
        $args = $closure();
        self::assertSame($request, $args[0]);
    }

    #[Test]
    public function multipleResolversInChain(): void
    {
        $request = new Request();
        $customValue = 'custom-value';

        $customResolver = new class ($customValue) implements ValueResolverInterface {
            public function __construct(private readonly string $value) {}

            public function supports(\ReflectionParameter $parameter): bool
            {
                return $parameter->getType() instanceof \ReflectionNamedType
                    && $parameter->getType()->getName() === 'string';
            }

            public function resolve(\ReflectionParameter $parameter): string
            {
                return $this->value;
            }
        };

        $resolver = new ArgumentResolver([
            new RequestValueResolver($request),
            $customResolver,
        ]);

        $target = new class {
            public function __invoke(Request $request, string $name): string
            {
                return '';
            }
        };

        $closure = $resolver->createResolver($target);

        self::assertNotNull($closure);
        $args = $closure();
        self::assertSame($request, $args[0]);
        self::assertSame($customValue, $args[1]);
    }
}

/**
 * @internal
 */
final class CountingValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly \stdClass $counter) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        return $parameter->getType() instanceof \ReflectionNamedType
            && $parameter->getType()->getName() === 'int';
    }

    public function resolve(\ReflectionParameter $parameter): int
    {
        return ++$this->counter->value;
    }
}
