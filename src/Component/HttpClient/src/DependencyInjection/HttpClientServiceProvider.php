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
