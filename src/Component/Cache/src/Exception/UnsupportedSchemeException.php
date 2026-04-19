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

namespace WPPack\Component\Cache\Exception;

use WPPack\Component\Dsn\Dsn;

final class UnsupportedSchemeException extends \LogicException implements ExceptionInterface
{
    /** @param list<string> $supported */
    public function __construct(Dsn $dsn, ?string $name = null, array $supported = [])
    {
        $message = sprintf('The "%s" scheme is not supported.', $dsn->getScheme());
        if ($name !== null && $supported !== []) {
            $message .= sprintf(' Supported schemes for "%s": %s.', $name, implode(', ', $supported));
        }
        parent::__construct($message);
    }
}
