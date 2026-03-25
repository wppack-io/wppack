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

namespace WpPack\Component\Security\ValueResolver;

use WpPack\Component\HttpFoundation\ValueResolverInterface;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Security;

final class CurrentUserValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        return $parameter->getAttributes(CurrentUser::class) !== [];
    }

    public function resolve(\ReflectionParameter $parameter): ?\WP_User
    {
        return $this->security->getUser();
    }
}
