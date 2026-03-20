<?php

declare(strict_types=1);

namespace WpPack\Component\Feed;

use WpPack\Component\Feed\Attribute\AsFeed;

abstract class AbstractFeed
{
    public readonly string $slug;
    public readonly string $label;

    public function __construct()
    {
        $attribute = $this->resolveAttribute();

        $this->slug = $attribute->slug;
        $this->label = $attribute->label;
    }

    abstract public function render(): void;

    public function register(): void
    {
        add_feed($this->slug, $this->render(...));
    }

    private function resolveAttribute(): AsFeed
    {
        $reflection = new \ReflectionClass($this);
        /** @var list<\ReflectionAttribute<AsFeed>> $attributes */
        $attributes = $reflection->getAttributes(AsFeed::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsFeed] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
