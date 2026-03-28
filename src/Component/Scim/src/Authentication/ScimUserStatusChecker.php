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

namespace WpPack\Component\Scim\Authentication;

use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\WordPressEvent;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\User\UserRepositoryInterface;

#[AsEventListener(event: 'wp_authenticate_user', priority: 30)]
final readonly class ScimUserStatusChecker
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function __invoke(WordPressEvent $event): void
    {
        $user = $event->filterValue;

        if (!$user instanceof \WP_User) {
            return;
        }

        $active = $this->userRepository->getMeta($user->ID, ScimConstants::META_ACTIVE, true);

        if ($active === '0') {
            $event->filterValue = new \WP_Error(
                'scim_user_deactivated',
                'This account has been deactivated.',
            );
        }
    }
}
