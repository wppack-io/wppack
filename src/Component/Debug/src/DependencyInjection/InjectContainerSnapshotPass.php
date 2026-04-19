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

namespace WPPack\Component\Debug\DependencyInjection;

use WPPack\Component\Debug\DataCollector\ContainerDataCollector;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;

final class InjectContainerSnapshotPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(ContainerDataCollector::class)) {
            return;
        }

        $snapshot = $this->buildSnapshot($builder);

        $builder->findDefinition(ContainerDataCollector::class)
            ->addMethodCall('setContainerSnapshot', [$snapshot]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(ContainerBuilder $builder): array
    {
        $services = [];
        $publicCount = 0;
        $privateCount = 0;
        $autowiredCount = 0;
        $lazyCount = 0;

        foreach ($builder->all() as $id => $definition) {
            $isPublic = $definition->isPublic();
            $isAutowired = $definition->isAutowired();
            $isLazy = $definition->isLazy();

            $services[$id] = [
                'class' => $definition->getClass() ?? $id,
                'public' => $isPublic,
                'autowired' => $isAutowired,
                'lazy' => $isLazy,
                'tags' => $definition->getTags(),
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

        $compilerPasses = array_map(
            static fn(CompilerPassInterface $pass): string => $pass::class,
            $builder->getCompilerPasses(),
        );

        $taggedServices = [];
        foreach ($builder->getSymfonyBuilder()->findTags() as $tag) {
            $taggedServices[$tag] = array_keys($builder->findTaggedServiceIds($tag));
        }

        $parameters = [];

        try {
            $parameters = $builder->getSymfonyBuilder()->getParameterBag()->all();
        } catch (\Throwable) {
            // Parameter bag may throw during compilation
        }

        return [
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
