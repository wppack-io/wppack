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

namespace WpPack\Component\Shortcode;

use WpPack\Component\OptionsResolver\OptionsResolver;
use WpPack\Component\Shortcode\Attribute\AsShortcode;

abstract class AbstractShortcode
{
    public readonly string $name;
    public readonly string $description;

    private ?OptionsResolver $cachedResolver = null;
    private ?bool $hasConfigureAttributesOverride = null;

    public function __construct()
    {
        $attribute = $this->resolveShortcodeAttribute();

        $this->name = $attribute->name;
        $this->description = $attribute->description;
    }

    /**
     * @param array<string, mixed> $atts
     */
    abstract public function render(array $atts, string $content): string;

    /**
     * Configure attribute defaults, allowed values, and normalizers.
     *
     * Override this method to declare shortcode attributes.
     */
    protected function configureAttributes(OptionsResolver $resolver): void {}

    /**
     * Resolve shortcode attributes through OptionsResolver.
     *
     * If configureAttributes() is not overridden, returns raw atts unchanged.
     * When WordPress is available, shortcode_atts() is called to apply
     * the shortcode_atts_{shortcode} filter for plugin compatibility.
     *
     * @param array<string, string> $atts
     * @return array<string, mixed>
     */
    public function resolveAttributes(array $atts): array
    {
        if (!$this->hasConfigureAttributesOverride()) {
            return $atts;
        }

        if ($this->cachedResolver === null) {
            $this->cachedResolver = new OptionsResolver();
            $this->configureAttributes($this->cachedResolver);
        }

        // Apply shortcode_atts() for WordPress filter compatibility
        $defaults = $this->cachedResolver->resolve([]);
        $atts = shortcode_atts($defaults, $atts, $this->name);

        return $this->cachedResolver->resolve($atts);
    }

    private function hasConfigureAttributesOverride(): bool
    {
        if ($this->hasConfigureAttributesOverride === null) {
            $method = new \ReflectionMethod($this, 'configureAttributes');
            $this->hasConfigureAttributesOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasConfigureAttributesOverride;
    }

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
