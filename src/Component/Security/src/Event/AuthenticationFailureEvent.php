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

namespace WPPack\Component\Security\Event;

use WPPack\Component\EventDispatcher\Event;
use WPPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationFailureEvent extends Event
{
    public function __construct(
        private readonly AuthenticationException $exception,
    ) {}

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }
}
