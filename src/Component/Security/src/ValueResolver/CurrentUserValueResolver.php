<?php

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
