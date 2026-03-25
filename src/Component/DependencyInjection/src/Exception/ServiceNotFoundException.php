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

namespace WpPack\Component\DependencyInjection\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends \InvalidArgumentException implements ExceptionInterface, NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Service "%s" not found.', $id));
    }
}
