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

namespace WpPack\Component\Scim\Exception;

class InvalidPatchException extends ScimException
{
    public function __construct(string $message = 'Invalid patch operation.', ?string $scimType = 'invalidPath', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $scimType, $previous);
    }
}
