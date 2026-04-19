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

namespace WPPack\Component\HttpFoundation;

final class ArgumentResolver
{
    /** @param iterable<ValueResolverInterface> $resolvers */
    public function __construct(
        private readonly iterable $resolvers,
    ) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($parameter)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(\ReflectionParameter $parameter): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($parameter)) {
                return $resolver->resolve($parameter);
            }
        }

        throw new \RuntimeException(\sprintf(
            'No value resolver supports parameter "$%s" of type "%s".',
            $parameter->getName(),
            $parameter->getType() instanceof \ReflectionNamedType ? $parameter->getType()->getName() : 'unknown',
        ));
    }

    /**
     * Creates a closure that resolves DI parameters for a method.
     *
     * Returns null if the method does not exist or has no resolvable parameters.
     * Parameters not supported by any resolver are skipped.
     */
    public function createResolver(object $target, string $methodName = '__invoke'): ?\Closure
    {
        if (!method_exists($target, $methodName)) {
            return null;
        }

        $method = new \ReflectionMethod($target, $methodName);
        $params = $method->getParameters();

        if ($params === []) {
            return null;
        }

        /** @var array<int, \ReflectionParameter> */
        $injectableParams = [];

        foreach ($params as $index => $parameter) {
            if ($this->supports($parameter)) {
                $injectableParams[$index] = $parameter;
            }
        }

        if ($injectableParams === []) {
            return null;
        }

        return fn(): array => array_map(
            fn(\ReflectionParameter $param): mixed => $this->resolve($param),
            $injectableParams,
        );
    }
}
