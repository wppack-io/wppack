<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\DependencyInjection\Attribute\AsAlias;
use WpPack\Component\DependencyInjection\Attribute\AsService;
use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ServiceDiscovery
{
    public function __construct(
        private readonly ContainerBuilder $builder,
    ) {}

    public function discover(string $directory, string $namespace): void
    {
        $directory = rtrim($directory, '/\\');
        $namespace = rtrim($namespace, '\\') . '\\';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($directory) + 1);
            $className = $namespace . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $relativePath,
            );

            if (!class_exists($className)) {
                continue;
            }

            $this->registerClass($className);
        }
    }

    private function registerClass(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        $configAttributes = $reflection->getAttributes(AsConfig::class);
        if ($configAttributes !== []) {
            $this->registerConfigClass($className, $configAttributes[0]->newInstance());

            return;
        }

        $attributes = $reflection->getAttributes(AsService::class);
        if ($attributes === []) {
            return;
        }

        /** @var AsService $asService */
        $asService = $attributes[0]->newInstance();

        $definition = $this->builder->register($className, $className);
        $definition->setPublic($asService->public);
        $definition->setLazy($asService->lazy);

        if ($asService->autowire) {
            $definition->autowire();
        }

        foreach ($asService->tags as $tag) {
            $definition->addTag($tag);
        }

        $this->processAutowireAttributes($reflection, $definition);
        $this->processAliasAttributes($reflection, $className);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAutowireAttributes(\ReflectionClass $reflection, Definition $definition): void
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(Autowire::class);
            if ($attributes === []) {
                continue;
            }

            /** @var Autowire $autowire */
            $autowire = $attributes[0]->newInstance();

            if ($autowire->env !== null) {
                $definition->setArgument(
                    '$' . $parameter->getName(),
                    sprintf('%%env(%s)%%', $autowire->env),
                );
            } elseif ($autowire->param !== null) {
                $definition->setArgument(
                    '$' . $parameter->getName(),
                    sprintf('%%%s%%', $autowire->param),
                );
            } elseif ($autowire->service !== null) {
                $definition->setArgument(
                    '$' . $parameter->getName(),
                    new Reference($autowire->service),
                );
            }
        }
    }

    private function registerConfigClass(string $className, AsConfig $asConfig): void
    {
        $definition = $this->builder->register($className, $className);
        $definition->setPublic(true);
        $definition->addTag('config.class');
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAliasAttributes(\ReflectionClass $reflection, string $className): void
    {
        $attributes = $reflection->getAttributes(AsAlias::class);
        foreach ($attributes as $attribute) {
            /** @var AsAlias $asAlias */
            $asAlias = $attribute->newInstance();
            $this->builder->setAlias($asAlias->id, $className);
        }
    }
}
