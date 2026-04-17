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
 * Raised when the underlying service returns a rate-limit / throttling
 * response (HTTP 429, AWS ThrottlingException, Postgres
 * SQLSTATE 53300 "too many connections"). Callers can opt to retry with
 * exponential backoff instead of surfacing the failure to the end user.
 */
class DriverThrottledException extends DriverException {}
