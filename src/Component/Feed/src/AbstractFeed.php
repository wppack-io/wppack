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

namespace WPPack\Component\Feed;

use WPPack\Component\Feed\Attribute\AsFeed;

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
