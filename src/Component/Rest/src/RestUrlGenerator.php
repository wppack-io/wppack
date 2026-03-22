<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

final class RestUrlGenerator
{
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
}
