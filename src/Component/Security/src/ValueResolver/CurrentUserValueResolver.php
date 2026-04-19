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

namespace WPPack\Component\Security\ValueResolver;

use WPPack\Component\HttpFoundation\ValueResolverInterface;
use WPPack\Component\Security\Attribute\CurrentUser;
use WPPack\Component\Security\Security;

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
