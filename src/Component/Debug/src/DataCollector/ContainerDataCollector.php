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

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'container', priority: 75)]
final class ContainerDataCollector extends AbstractDataCollector
{
    /** @var array<string, mixed>|null */
    private ?array $containerSnapshot = null;

    public function __construct(
        private readonly ?object $containerBuilder = null,
    ) {}

    public function getName(): string
    {
        return 'container';
    }

    public function getLabel(): string
    {
        return 'Container';
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function setContainerSnapshot(array $snapshot): void
    {
        $this->containerSnapshot = $snapshot;
    }

    public function collect(): void
    {
        if ($this->containerSnapshot !== null) {
            $this->data = $this->containerSnapshot;

            return;
        }

        if ($this->containerBuilder !== null) {
            $this->collectFromContainer();

            return;
        }

        $this->data = [
            'service_count' => 0,
            'public_count' => 0,
            'private_count' => 0,
            'autowired_count' => 0,
            'lazy_count' => 0,
            'services' => [],
            'compiler_passes' => [],
            'tagged_services' => [],
            'parameters' => [],
        ];
    }

    public function getIndicatorValue(): string
    {
        $count = (int) ($this->data['service_count'] ?? 0);

        return $count > 0 ? (string) $count : '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->containerSnapshot = null;
    }

    private function collectFromContainer(): void
    {
        $builder = $this->containerBuilder;
        $services = [];
        $publicCount = 0;
        $privateCount = 0;
        $autowiredCount = 0;
        $lazyCount = 0;

        if (method_exists($builder, 'all')) {
            foreach ($builder->all() as $id => $definition) {
                // method_exists accepts class-string too; the calls below
                // require an instance, so guard once.
                if (!\is_object($definition)) {
                    continue;
                }

                $isPublic = method_exists($definition, 'isPublic') && $definition->isPublic();
                $isAutowired = method_exists($definition, 'isAutowired') && $definition->isAutowired();
                $isLazy = method_exists($definition, 'isLazy') && $definition->isLazy();

                $services[$id] = [
                    'class' => method_exists($definition, 'getClass') ? ($definition->getClass() ?? $id) : $id,
                    'public' => $isPublic,
                    'autowired' => $isAutowired,
                    'lazy' => $isLazy,
                    'tags' => method_exists($definition, 'getTags') ? array_keys($definition->getTags()) : [],
                ];

                if ($isPublic) {
                    $publicCount++;
                } else {
                    $privateCount++;
                }
                if ($isAutowired) {
                    $autowiredCount++;
                }
                if ($isLazy) {
                    $lazyCount++;
                }
            }
        }

        $compilerPasses = [];
        if (method_exists($builder, 'getCompilerPassConfig')) {
            $config = $builder->getCompilerPassConfig();
            if (\is_object($config) && method_exists($config, 'getPasses')) {
                foreach ($config->getPasses() as $pass) {
                    $compilerPasses[] = $pass::class;
                }
            }
        }

        $taggedServices = [];
        if (method_exists($builder, 'findTags')) {
            foreach ($builder->findTags() as $tag) {
                $taggedIds = method_exists($builder, 'findTaggedServiceIds') ? array_keys($builder->findTaggedServiceIds($tag)) : [];
                $taggedServices[$tag] = $taggedIds;
            }
        }

        $parameters = [];
        if (method_exists($builder, 'getParameterBag')) {
            $bag = $builder->getParameterBag();
            if (\is_object($bag) && method_exists($bag, 'all')) {
                $parameters = $bag->all();
            }
        }

        $this->data = [
            'service_count' => count($services),
            'public_count' => $publicCount,
            'private_count' => $privateCount,
            'autowired_count' => $autowiredCount,
            'lazy_count' => $lazyCount,
            'services' => $services,
            'compiler_passes' => $compilerPasses,
            'tagged_services' => $taggedServices,
            'parameters' => $parameters,
        ];
    }
}
