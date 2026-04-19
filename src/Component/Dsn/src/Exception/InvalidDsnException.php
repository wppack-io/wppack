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

namespace WPPack\Component\Dsn\Exception;

class InvalidDsnException extends \InvalidArgumentException implements ExceptionInterface
{
    public static function invalidFormat(string $dsn): self
    {
        return new self(\sprintf('The DSN "%s" is invalid.', $dsn));
    }

    public static function missingScheme(string $dsn): self
    {
        return new self(\sprintf('The DSN "%s" must contain a scheme.', $dsn));
    }
}
