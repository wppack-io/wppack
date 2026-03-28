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

namespace WpPack\Component\Security\Bridge\OAuth\Event;

use WpPack\Component\EventDispatcher\Event;

final class OAuthUserProvisionFailedEvent extends Event
{
    public function __construct(
        private readonly string $subject,
        private readonly \WP_Error $error,
    ) {}

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getError(): \WP_Error
    {
        return $this->error;
    }
}
