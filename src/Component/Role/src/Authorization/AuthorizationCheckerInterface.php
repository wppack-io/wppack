<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Authorization;

interface AuthorizationCheckerInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool;
}
