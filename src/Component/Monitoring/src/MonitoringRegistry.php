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

class MonitoringRegistry
{
    /** @var list<MetricSource> */
    private array $sources = [];

    public function add(MetricSource $source): void
    {
        $this->sources[] = $source;
    }

    public function addFromProvider(MetricSourceProviderInterface $provider): void
    {
        foreach ($provider->getSources() as $source) {
            $this->add($source);
        }
    }

    /**
     * @return list<MetricSource>
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * @return list<MetricSource>
     */
    public function byProvider(string $provider): array
    {
        return array_values(array_filter(
            $this->sources,
            static fn(MetricSource $s): bool => $s->provider === $provider,
        ));
    }

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return array_values(array_unique(array_map(
            static fn(MetricSource $s): string => $s->provider,
            $this->sources,
        )));
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return array_values(array_unique(array_map(
            static fn(MetricSource $s): string => $s->group,
            $this->sources,
        )));
    }
}
