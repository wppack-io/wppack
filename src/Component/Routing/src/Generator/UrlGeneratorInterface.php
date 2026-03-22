<?php

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
