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

namespace WpPack\Component\DependencyInjection\ValueResolver;

use Psr\Container\ContainerInterface;
use WpPack\Component\HttpFoundation\ValueResolverInterface;

final class ContainerValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        return $this->container->has($type->getName());
    }

    public function resolve(\ReflectionParameter $parameter): mixed
    {
        /** @var \ReflectionNamedType $type */
        $type = $parameter->getType();

        return $this->container->get($type->getName());
    }
}
