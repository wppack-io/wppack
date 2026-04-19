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

namespace WPPack\Component\Routing\Generator;

use WPPack\Component\Routing\Exception\MissingParametersException;
use WPPack\Component\Routing\Exception\RouteNotFoundException;

interface UrlGeneratorInterface
{
    /**
     * @param array<string, string|int> $parameters
     *
     * @throws RouteNotFoundException
     * @throws MissingParametersException
     */
    public function generate(string $name, array $parameters = []): string;
}
