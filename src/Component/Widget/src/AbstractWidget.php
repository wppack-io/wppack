<?php

declare(strict_types=1);

namespace WpPack\Component\Widget;

use WpPack\Component\Widget\Attribute\Widget;

abstract class AbstractWidget extends \WP_Widget
{
    public function __construct()
    {
        $attribute = $this->resolveWidgetAttribute();

        parent::__construct(
            $attribute->id,
            $attribute->name,
            ['description' => $attribute->description],
        );
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $instance
     */
    abstract protected function render(array $args, array $instance): string;

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $instance
     */
    public function widget($args, $instance): void
    {
        echo $this->render($args, $instance);
    }

    /**
     * @param array<string, mixed> $instance
     */
    public function form($instance): void {}

    /**
     * @param array<string, mixed> $newInstance
     * @param array<string, mixed> $oldInstance
     * @return array<string, mixed>
     */
    public function update($newInstance, $oldInstance): array
    {
        return $newInstance;
    }

    private function resolveWidgetAttribute(): Widget
    {
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes(Widget::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[Widget] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
