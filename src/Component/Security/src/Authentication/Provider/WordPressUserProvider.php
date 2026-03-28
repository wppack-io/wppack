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

namespace WpPack\Component\Security\Authentication\Provider;

use WpPack\Component\Security\Exception\UserNotFoundException;
use WpPack\Component\User\UserRepositoryInterface;

final class WordPressUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function loadUserByIdentifier(string $identifier): \WP_User
    {
        $user = $this->userRepository->findByLogin($identifier);

        if ($user === null) {
            $user = $this->userRepository->findByEmail($identifier);
        }

        if ($user === null) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }
}
