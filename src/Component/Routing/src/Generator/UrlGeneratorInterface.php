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

namespace WpPack\Component\Routing\Generator;

use WpPack\Component\Routing\Exception\MissingParametersException;
use WpPack\Component\Routing\Exception\RouteNotFoundException;

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
