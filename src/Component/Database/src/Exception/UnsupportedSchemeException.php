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

use WPPack\Component\Dsn\Dsn;

class UnsupportedSchemeException extends \InvalidArgumentException implements ExceptionInterface
{
    public function __construct(Dsn $dsn)
    {
        parent::__construct(\sprintf('The scheme "%s" is not supported.', $dsn->getScheme()));
    }
}
