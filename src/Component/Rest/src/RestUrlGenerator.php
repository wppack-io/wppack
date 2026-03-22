<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\Rest\Exception\MissingParametersException;

final class RestUrlGenerator
{
    public function __construct(
        private readonly RestRegistry $registry,
    ) {}

    /**
     * @see rest_url()
     */
    public function url(string $path = ''): string
    {
        return rest_url($path);
    }

    /**
     * @see rest_get_url_prefix()
     */
    public function prefix(): string
    {
        return rest_get_url_prefix();
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public function generate(string $name, array $parameters = []): string
    {
        $entry = $this->registry->get($name);
        $path = $entry->path;

        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }

        $missing = RestEntry::extractParams($path);
        if ($missing !== []) {
            throw new MissingParametersException(sprintf(
                'Missing parameters "%s" for route "%s".',
                implode('", "', $missing),
                $name,
            ));
        }

        return rest_url($entry->namespace . $path);
    }
}
