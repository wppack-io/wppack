<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\DependencyInjection;

use Psr\Http\Client\ClientInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\SafeHttpClient;

final class HttpClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(HttpClient::class);
        $builder->setAlias(ClientInterface::class, HttpClient::class);
        $builder->register(SafeHttpClient::class);
    }
}
