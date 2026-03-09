<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Provider;

use WpPack\Component\Security\Exception\UserNotFoundException;

interface UserProviderInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function loadUserByIdentifier(string $identifier): \WP_User;
}
