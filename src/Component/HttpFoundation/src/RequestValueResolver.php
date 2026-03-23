<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

final class RequestValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType && $type->getName() === Request::class;
    }

    public function resolve(\ReflectionParameter $parameter): Request
    {
        return $this->request;
    }
}
