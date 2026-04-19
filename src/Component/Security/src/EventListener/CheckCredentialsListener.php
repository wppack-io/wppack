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

namespace WPPack\Component\Security\EventListener;

use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Event\CheckPassportEvent;
use WPPack\Component\Security\Exception\InvalidCredentialsException;

final class CheckCredentialsListener
{
    public function __invoke(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $badge = $passport->getBadge(CredentialsBadge::class);

        if (!$badge instanceof CredentialsBadge) {
            return;
        }

        $user = $passport->getUser();

        if (!wp_check_password($badge->getPassword(), $user->user_pass, $user->ID)) {
            throw new InvalidCredentialsException();
        }

        $badge->markResolved();
    }
}
