<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authentication\Provider;

use WpPack\Component\Security\Exception\UserNotFoundException;

final class WordPressUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): \WP_User
    {
        $user = get_user_by('login', $identifier);

        if ($user === false) {
            $user = get_user_by('email', $identifier);
        }

        if ($user === false) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }
}
