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

namespace WpPack\Component\Storage\Exception;

final class UnsupportedOperationException extends \LogicException implements ExceptionInterface
{
    public function __construct(string $operation, string $adapterName)
    {
        parent::__construct(sprintf('The "%s" operation is not supported by the "%s" adapter.', $operation, $adapterName));
    }
}
