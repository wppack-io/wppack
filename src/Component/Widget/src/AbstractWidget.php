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

namespace WpPack\Component\Widget;

use WpPack\Component\Templating\TemplateRendererInterface;
use WpPack\Component\Widget\Attribute\AsWidget;

abstract class AbstractWidget extends \WP_Widget
{
    private ?TemplateRendererInterface $templateRenderer = null;

    public function __construct()
    {
        $attribute = $this->resolveWidgetAttribute();

        parent::__construct(
            $attribute->id,
            $attribute->label,
            ['description' => $attribute->description],
        );
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $instance
     */
    public function widget($args, $instance): void
    {
        echo $this($args, $instance);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function render(string $template, array $context = []): string
    {
        if ($this->templateRenderer === null) {
            throw new \LogicException(sprintf(
                'A TemplateRendererInterface is not available. Call setTemplateRenderer() to use render() in "%s".',
                static::class,
            ));
        }

        return $this->templateRenderer->render($template, $context);
    }

    /**
     * @param array<string, mixed> $instance
     */
    public function form($instance): void
    {
        if (!method_exists($this, 'configure')) {
            return;
        }

        echo $this->configure($instance);
    }

    /**
     * @param array<string, mixed> $newInstance
     * @param array<string, mixed> $oldInstance
     * @return array<string, mixed>
     */
    public function update($newInstance, $oldInstance): array
    {
        return $newInstance;
    }

    /** @internal */
    public function setTemplateRenderer(TemplateRendererInterface $renderer): void
    {
        $this->templateRenderer = $renderer;
    }

    private function resolveWidgetAttribute(): AsWidget
    {
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes(AsWidget::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsWidget] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
