<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient;

class SafeHttpClient extends HttpClient
{
    public function __construct()
    {
        $this->options['reject_unsafe_urls'] = true;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $options['reject_unsafe_urls'] = true;

        return parent::withOptions($options);
    }
}
