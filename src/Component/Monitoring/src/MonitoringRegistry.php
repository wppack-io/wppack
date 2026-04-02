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

namespace WpPack\Component\Monitoring;

final class MonitoringRegistry
{
    /** @var list<MonitoringProvider> */
    private array $providers = [];

    public function addProvider(MonitoringProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    public function addFromSource(MonitoringProviderInterface $source): void
    {
        foreach ($source->getProviders() as $provider) {
            $this->addProvider($provider);
        }
    }

    /**
     * @return list<MonitoringProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $id): ?MonitoringProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->id === $id) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function bridges(): array
    {
        return array_values(array_unique(array_map(
            static fn(MonitoringProvider $p): string => $p->bridge,
            $this->providers,
        )));
    }
}
