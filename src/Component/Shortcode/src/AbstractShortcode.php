<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode;

use WpPack\Component\Shortcode\Attribute\AsShortcode;

abstract class AbstractShortcode
{
    public readonly string $name;
    public readonly string $description;

    public function __construct()
    {
        $attribute = $this->resolveShortcodeAttribute();

        $this->name = $attribute->name;
        $this->description = $attribute->description;
    }

    /**
     * @param array<string, string> $atts
     */
    abstract public function render(array $atts, string $content): string;

    private function resolveShortcodeAttribute(): AsShortcode
    {
        $reflection = new \ReflectionClass($this);
        /** @var list<\ReflectionAttribute<AsShortcode>> $attributes */
        $attributes = $reflection->getAttributes(AsShortcode::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsShortcode] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
