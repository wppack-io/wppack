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

namespace WpPack\Component\Database\Exception;

/**
 * Raised when the driver cannot get a response from the backing service
 * within the expected window — HTTP 504, socket read timeouts, async-aws
 * RequestTimeout responses. Safe-to-retry classification depends on
 * whether the original query was idempotent; that call is up to the
 * caller.
 */
class DriverTimeoutException extends DriverException {}
