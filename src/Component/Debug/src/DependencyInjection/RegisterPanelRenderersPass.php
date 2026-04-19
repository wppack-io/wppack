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

use WPPack\Component\Debug\Attribute\AsPanelRenderer;
use WPPack\Component\Debug\Toolbar\ToolbarRenderer;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;

final class RegisterPanelRenderersPass implements CompilerPassInterface
{
    public const TAG = 'debug.panel_renderer';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(ToolbarRenderer::class)) {
            return;
        }

        $toolbarDefinition = $builder->findDefinition(ToolbarRenderer::class);

        $renderers = [];

        foreach ($builder->all() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsPanelRenderer::class);

            $isRenderer = $definition->hasTag(self::TAG) || $attributes !== [];

            if (!$isRenderer) {
                continue;
            }

            $priority = 0;
            if ($attributes !== []) {
                $attr = $attributes[0]->newInstance();
                $priority = $attr->priority;
            }

            $renderers[] = [
                'id' => $definition->getId(),
                'priority' => $priority,
            ];
        }

        usort($renderers, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        foreach ($renderers as $renderer) {
            $toolbarDefinition->addMethodCall('addPanelRenderer', [new Reference($renderer['id'])]);
        }
    }
}
