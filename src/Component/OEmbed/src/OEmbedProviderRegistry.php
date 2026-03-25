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

namespace WpPack\Component\OEmbed;

final class OEmbedProviderRegistry
{
    /** @var list<OEmbedProviderInterface> */
    private array $providers = [];

    /** @var array<string, OEmbedProviderDefinition> */
    private array $definitions = [];

    public function addProvider(OEmbedProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Register all collected providers via wp_oembed_add_provider().
     *
     * Intended to be called on the init hook.
     */
    public function register(): void
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getProviders() as $definition) {
                $this->definitions[$definition->format] = $definition;
            }
        }

        foreach ($this->definitions as $definition) {
            wp_oembed_add_provider($definition->format, $definition->endpoint, $definition->regex);
        }
    }

    public function addDefinition(string $format, string $endpoint, bool $regex = false): void
    {
        $definition = new OEmbedProviderDefinition($format, $endpoint, $regex);
        $this->definitions[$format] = $definition;

        wp_oembed_add_provider($format, $endpoint, $regex);
    }

    public function unregister(string $format): void
    {
        unset($this->definitions[$format]);

        wp_oembed_remove_provider($format);
    }

    public function hasProvider(string $format): bool
    {
        return isset($this->definitions[$format]);
    }

    /**
     * @return list<OEmbedProviderDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
