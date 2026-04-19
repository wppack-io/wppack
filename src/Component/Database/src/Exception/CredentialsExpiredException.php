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

namespace WPPack\Component\Database\Exception;

/**
 * Raised when the underlying service rejects our credentials — typically
 * an expired IAM token (Aurora DSQL, RDS IAM auth) or a rotated Secrets
 * Manager secret (RDS Data API). Callers can respond by refreshing the
 * credential source and retrying.
 */
class CredentialsExpiredException extends DriverException {}
