<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'container', priority: 75)]
final class ContainerDataCollector extends AbstractDataCollector
{
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

    public function collect(): void
    {
        if ($this->containerBuilder === null) {
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

            return;
        }

        $this->collectFromContainer();
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

    private function collectFromContainer(): void
    {
        $builder = $this->containerBuilder;
        $services = [];
        $publicCount = 0;
        $privateCount = 0;
        $autowiredCount = 0;
        $lazyCount = 0;

        if (method_exists($builder, 'getDefinitions')) {
            foreach ($builder->getDefinitions() as $id => $definition) {
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
            if (method_exists($config, 'getPasses')) {
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
            if (method_exists($bag, 'all')) {
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
