<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Generator;

use WpPack\Component\Routing\Exception\MissingParametersException;
use WpPack\Component\Routing\RouteEntry;
use WpPack\Component\Routing\RouteRegistry;

final class UrlGenerator implements UrlGeneratorInterface
{
    public function __construct(
        private readonly RouteRegistry $routes,
    ) {}

    public function generate(string $name, array $parameters = []): string
    {
        $entry = $this->routes->get($name);
        $path = $entry->path;

        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        $missing = RouteEntry::extractParams($path);
        if ($missing !== []) {
            throw new MissingParametersException(sprintf(
                'Missing parameters "%s" for route "%s".',
                implode('", "', $missing),
                $name,
            ));
        }

        return '/' . ltrim($path, '/');
    }
}
