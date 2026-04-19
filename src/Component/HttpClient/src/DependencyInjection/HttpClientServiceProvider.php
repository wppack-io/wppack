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

namespace WPPack\Component\HttpClient\DependencyInjection;

use Psr\Http\Client\ClientInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpClient\HttpClient;
use WPPack\Component\HttpClient\SafeHttpClient;

final class HttpClientServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(HttpClient::class);
        $builder->setAlias(ClientInterface::class, HttpClient::class);
        $builder->register(SafeHttpClient::class);
    }
}
