<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

interface ValueResolverInterface
{
    /**
     * Whether this resolver can handle the given parameter.
     * Must be a cheap reflection-only check (no side effects).
     */
    public function supports(\ReflectionParameter $parameter): bool;

    /**
     * Resolves the parameter value. Only called if supports() returns true.
     */
    public function resolve(\ReflectionParameter $parameter): mixed;
}
